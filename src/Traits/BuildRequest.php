<?php

/**
 * This is a lib to crawl the Academic Network Systems.
 * You can achieve easely the querying of grade/schedule/cet/free classroom ...
 *
 * @author Ning Luo <luoning@Luoning.me>
 * @link https://github.com/lndj/Lcrawl
 * @license  MIT
 */

namespace ZfSpider\Traits;

/**
 * This is a trait to build get & post request.
 */
trait BuildRequest
{


    private $request_options;

    /**
     * Build the get request.
     *
     * @param string $uri
     * @param array $param
     * @param array $headers
     * @param bool $isAsync
     * @return mixed
     */
    public function get($uri, $param = [], $headers = [], $isAsync = false)
    {
        $query_param = array_merge(['xh' => $this->stu_id], $param);

        $query = array_merge(
            $this->request_options,
            [
                'query' => $query_param,
                'headers' => $headers,
                'cookies' => $this->cookie
            ]
        );
        //If use getAll(), use the Async request.
        return $isAsync
            ? $this->client->getAsync($uri, $query)
            : $this->client->get($uri, $query);
    }

    /**
     * Build the POST request.
     *
     * @param $uri
     * @param $query
     * @param $param
     * @param array $headers A array of headers.
     * @param bool $isAsync If use getAll(),  by Async request.
     * @return mixed
     */
    public function post($uri, $query, $param, $headers = [], $isAsync = false)
    {
        $query_param = array_merge(['xh' => $this->stu_id], $query);
        $post = array_merge(
            $this->request_options,
            [
                'query' => $query_param,
                'headers' => $headers,
                'cookies' => $this->cookie,
                'form_params' => $param,
            ]
        );
        //If use getAll(), use the Async request.
        return $isAsync
            ? $this->client->postAsync($uri, $post)
            : $this->client->post($uri, $post);
    }
}