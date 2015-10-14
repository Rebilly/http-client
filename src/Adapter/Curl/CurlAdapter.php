<?php
/**
 * This file is part of the HTTP Client package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @see http://rebilly.com
 */

namespace Rebilly\HttpClient\Adapter\Curl;

use Rebilly\HttpClient\Adapter;
use RuntimeException;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * Class CurlAdapter.
 *
 * @author Veaceslav Medvedev <veaceslav.medvedev@rebilly.com>
 * @version 0.1
 */
class CurlAdapter implements Adapter
{
    /**
     * @var array The base options
     */
    private $options = [];

    /**
     * Constructor
     *
     * @see http://php.net/manual/en/function.curl-setopt.php
     *
     * @param array $options The base options
     */
    public function __construct(array $options = [])
    {
        $this->options = array_replace(
            // Default CURL options
            [
                CURLOPT_USERAGENT => @$_SERVER['HTTP_USER_AGENT'] ?: 'Curl/PHP' . PHP_VERSION,
            ],
            $options,
            // Required options
            [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true,
            ],
            /*
             * Ensuring security options
             * @link http://stackoverflow.com/questions/13740933/
             */
            [
                CURLOPT_SSL_VERIFYPEER => 1,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]
        );
    }

    /**
     * @param Request $request
     * @param Response $response
     *
     * @return Response
     */
    public function __invoke(Request $request, Response $response)
    {
        $options = [];

        // Headers
        $headers = [];
        foreach ($request->getHeaders() as $name => $values) {
            $headers[] = sprintf('%s: %s', $name, implode(', ', $values));
        }
        $options[CURLOPT_HTTPHEADER] = $headers;

        // Url
        $options[CURLOPT_URL] = (string) $request->getUri();

        switch ($request->getMethod()) {
            case 'HEAD':
                $options[CURLOPT_NOBODY] = true;
                break;
            case 'GET':
                $options[CURLOPT_HTTPGET] = true;
                break;
            case 'POST':
                $options[CURLOPT_POST] = true;
                $options[CURLOPT_POSTFIELDS] = (string) $request->getBody();

                // Don't duplicate the Content-Length header
                $request = $request->withoutHeader('Content-Length');
                $request = $request->withoutHeader('Transfer-Encoding');
                break;
            case 'PUT':
                // Write to memory/temp
                $file = fopen('php://temp/' . spl_object_hash($request), 'w+');
                $bytes = fwrite($file, (string) $request->getBody());
                rewind($file);

                $options[CURLOPT_PUT] = true;
                $options[CURLOPT_INFILE] = $file;
                $options[CURLOPT_INFILESIZE] = $bytes;

                $request = $request->withoutHeader('Content-Length');
                break;
            default:
                $options[CURLOPT_CUSTOMREQUEST] = $request->getMethod();
        }

        // If the Expect header is not present, prevent curl from adding it
        if (!$request->hasHeader('Expect')) {
            $options[CURLOPT_HTTPHEADER][] = 'Expect:';
        }

        // cURL sometimes adds a content-type by default. Prevent this.
        if (!$request->hasHeader('Content-Type')) {
            $options[CURLOPT_HTTPHEADER][] = 'Content-Type:';
        }

        list($body, $headers) = $this->execute($options);

        $headers = preg_split("#\r\n#", $headers, -1, PREG_SPLIT_NO_EMPTY);

        // Extract the version and status from the first header
        preg_match('#HTTP/(\d\.\d)\s(\d\d\d)\s(.*)#', array_shift($headers), $matches);
        array_shift($matches);
        list($protocolVersion, $statusCode, $reasonPhrase) = $matches;

        /** @var Response $response */
        $response->getBody()->write($body);
        $response = $response->withStatus($statusCode, $reasonPhrase);
        $response = $response->withProtocolVersion($protocolVersion);

        foreach ($headers as $line) {
            list($name, $values) = preg_split('#\s*:\s*#', $line, 2, PREG_SPLIT_NO_EMPTY);
            $values = preg_split('#\s*,\s*#', $values, -1, PREG_SPLIT_NO_EMPTY);

            $response = $response->withHeader($name, $values);
        }

        return $response;
    }

    /**
     * Execute cURL session.
     *
     * @param array $options
     *
     * @throws RuntimeException
     *
     * @return array
     */
    protected function execute(array $options = [])
    {
        $session = $this->createSession();
        $result = [];

        try {
            if ($session->open() === false) {
                throw new RuntimeException('Cannot initialize a cURL session');
            }

            $session->setOptions($options + $this->options);

            $result = $session->execute();

            if ($result === false) {
                // TODO: Add own exception
                throw new RuntimeException($session->getErrorMessage(), $session->getErrorCode());
            }

            $headerSize = $session->getInfo(CURLINFO_HEADER_SIZE);

            $result = [
                substr($result, $headerSize),
                substr($result, 0, $headerSize) ?: null
            ];
        } finally {
            $session->close();
        }

        return $result;
    }

    /**
     * @return CurlSession
     */
    protected function createSession()
    {
        return new CurlSession();
    }
}
