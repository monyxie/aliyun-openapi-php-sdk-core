<?php
/*
 * Licensed to the Apache Software Foundation (ASF) under one
 * or more contributor license agreements.  See the NOTICE file
 * distributed with this work for additional information
 * regarding copyright ownership.  The ASF licenses this file
 * to you under the Apache License, Version 2.0 (the
 * "License"); you may not use this file except in compliance
 * with the License.  You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

namespace Aliyun\Core\Regions;

use Aliyun\Core\Http\HttpHelper;

class LocationService
{
    const PRODUCT_NAME = 'Location';
    const DOMAIN = 'location.aliyuncs.com';
    const VERSION = '2015-06-12';
    const DESCRIBE_ENDPOINT_ACTION = 'DescribeEndpoints';
    const REGION = 'cn-hangzhou';
    const CACHE_EXPIRE_TIME = 3600;

    private $clientProfile;
    public static $cache = array();
    public static $lastClearTimePerProduct = array();
    public static $serviceDomain = LocationService::DOMAIN;

    function __construct($clientProfile)
    {
        $this->clientProfile = $clientProfile;
    }

    public function findProductDomain($regionId, $serviceCode, $endPointType, $product)
    {
        $key = $regionId . '#' . $product;
        @$domain = self::$cache[$key];
        if ($domain == null || $this->checkCacheIsExpire($key) == true) {
            $domain = $this->findProductDomainFromLocationService($regionId, $serviceCode, $endPointType);
            self::$cache[$key] = $domain;
        }

        return $domain;
    }

    public static function addEndPoint($regionId, $product, $domain)
    {
        $key = $regionId . '#' . $product;
        self::$cache[$key] = $domain;
        $lastClearTime = mktime(0, 0, 0, 1, 1, 2999);
        self::$lastClearTimePerProduct[$key] = $lastClearTime;
    }

    public static function modifyServiceDomain($domain)
    {
        self::$serviceDomain = $domain;
    }

    private function checkCacheIsExpire($key)
    {
        if (isset(self::$lastClearTimePerProduct[$key])) {
            $lastClearTime = self::$lastClearTimePerProduct[$key];
        } else {
            $lastClearTime = null;
        }

        if ($lastClearTime == null) {
            $lastClearTime = time();
            self::$lastClearTimePerProduct[$key] = $lastClearTime;
        }

        $now = time();
        $elapsedTime = $now - $lastClearTime;

        if ($elapsedTime > self::CACHE_EXPIRE_TIME) {
            $lastClearTime = time();
            self::$lastClearTimePerProduct[$key] = $lastClearTime;
            return true;
        }

        return false;
    }

    private function findProductDomainFromLocationService($regionId, $serviceCode, $endPointType)
    {
        $request = new \Aliyun\Core\Regions\DescribeEndpointRequest($regionId, $serviceCode, $endPointType);

        $signer = $this->clientProfile->getSigner();
        $credential = $this->clientProfile->getCredential();

        $requestUrl = $request->composeUrl($signer, $credential, self::$serviceDomain);

        $httpResponse = HttpHelper::curl($requestUrl, $request->getMethod(), null, $request->getHeaders());

        if (!$httpResponse->isSuccess()) {
            return null;
        }

        $respObj = json_decode($httpResponse->getBody());
        return $respObj->Endpoints->Endpoint[0]->Endpoint;
    }
}