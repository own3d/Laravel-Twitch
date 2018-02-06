<?php

namespace romanzipp\Twitch;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use romanzipp\Twitch\Exceptions\RequestRequiresAuthenticationException;
use romanzipp\Twitch\Exceptions\RequestRequiresClientIdException;
use romanzipp\Twitch\Helpers\Paginator;
use romanzipp\Twitch\Traits\ClipsTrait;
use romanzipp\Twitch\Traits\FollowsTrait;
use romanzipp\Twitch\Traits\GamesTrait;
use romanzipp\Twitch\Traits\Legacy\RootTrait as LegacyRootTrait;
use romanzipp\Twitch\Traits\OAuth2Trait;
use romanzipp\Twitch\Traits\StreamsTrait;
use romanzipp\Twitch\Traits\UsersTrait;
use romanzipp\Twitch\Traits\VideosTrait;

class Twitch
{
    use ClipsTrait;
    use FollowsTrait;
    use GamesTrait;
    use StreamsTrait;
    use UsersTrait;
    use VideosTrait;
    use OAuth2Trait;

    use LegacyRootTrait;

    const BASE_URI = 'https://api.twitch.tv/helix/';

    /**
     * Twitch token
     * @var string
     */
    protected $token = null;

    /**
     * Twitch client id
     * @var string
     */

    protected $clientId = null;

    /**
     * Guzzle is used to make http requests
     * @var \GuzzleHttp\Client
     */
    protected $client;

    /**
     * Paginator object
     * @var Paginator
     */
    protected $paginator;

    /**
     * Construction
     * @param string $token    Twitch OAuth Token
     * @param string $clientId Twitch client id
     */
    public function __construct(string $token = null, $clientId = null)
    {
        if ($token !== null) {
            $this->setToken($token);
        }

        if ($clientId !== null) {

            $this->setClientId($clientId);

        } elseif (config('twitch-api.client_id')) {

            $this->setClientId(config('twitch-api.client_id'));
        }

        $this->client = new Client([
            'base_uri' => self::BASE_URI,
        ]);
    }

    /**
     * Set clientId
     * @param string $clientId Twitch client id
     */
    public function setClientId($clientId)
    {
        $this->clientId = $clientId;
    }

    /**
     * Get clientId
     * @param  string $clientId clientId optional
     * @return string           clientId
     */
    public function getClientId(string $clientId = null)
    {
        if ($clientId !== null) {
            return $clientId;
        }

        if (!$this->clientId) {
            throw new RequestRequiresClientIdException();
        }

        return $this->clientId;
    }

    /**
     * Set Twitch OAuth Token
     * @param string $token OAuth token
     */
    public function setToken(string $token)
    {
        $this->token = $token;
    }

    /**
     * Set Twitch OAuth Token and return self instance
     * @param  string $token OAuth token
     * @return self
     */
    public function withToken(string $token)
    {
        $this->setToken($token);
        return $this;
    }

    /**
     * Get Twitch token
     * @param  mixed  $token Twitch OAuth Token
     * @return string Twitch token
     */
    public function getToken($token = null)
    {
        if (is_string($token)) {
            return $token;
        }

        if (!$this->token) {
            throw new RequestRequiresAuthenticationException();
        }

        return $this->token;
    }

    public function get(string $path = '', array $parameters = [], Paginator $paginator = null, string $token = null)
    {
        if ($token) {
            $this->setToken($token);
        }

        return $this->query('GET', $path, $parameters, $paginator);
    }

    public function post(string $path = '', array $parameters = [], Paginator $paginator = null, string $token = null)
    {
        if ($token) {
            $this->setToken($token);
        }

        return $this->query('POST', $path, $parameters, $paginator);
    }

    public function put(string $path = '', array $parameters = [], Paginator $paginator = null, string $token = null)
    {
        if ($token) {
            $this->setToken($token);
        }

        return $this->query('PUT', $path, $parameters, $paginator);
    }

    /**
     * Execute query
     * @param  string $method     HTTP method
     * @param  string $path       Query path
     * @param  array  $parameters Query parameters
     * @param  mixed  $token      Token String or true/false to obtain by setToken method
     * @return Result             Result object
     */
    public function query(string $method = 'GET', string $path = '', array $parameters = [], Paginator $paginator = null): Result
    {
        if ($paginator) {
            $parameters[$paginator->action] = $paginator->cursor();
        }

        $uri = $this->generateUrl($path, $parameters);

        $headers = [
            'Client-ID' => $this->getClientId(),
        ];

        if ($this->token) {
            $headers['Authorization'] = 'Bearer ' . $this->getToken();
        }

        try {
            $request = new Request($method, $uri, $headers);

            $response = $this->client->send($request);

            $result = new Result($response, null, $paginator);

        } catch (RequestException $e) {

            $result = new Result(null, $e, $paginator);
        }

        return $result;
    }

    /**
     * Generate URL for API
     * @param  string      $url        Query uri
     * @param  null|string $token      Auth token, if required
     * @param  array       $parameters Query parameters
     * @return string                  Full query url
     */
    public function generateUrl(string $url, array $parameters): string
    {
        foreach ($parameters as $optionKey => $option) {

            $data = !is_array($option) ? [$option] : $option;

            foreach ($data as $key => $value) {
                $url .= (parse_url($url, PHP_URL_QUERY) ? '&' : '?') . $optionKey . '=' . $value;
            }
        }

        return $url;
    }
}
