<?php

namespace ccxt\pro;

// PLEASE DO NOT EDIT THIS FILE, IT IS GENERATED AND WILL BE OVERWRITTEN:
// https://github.com/ccxt/ccxt/blob/master/CONTRIBUTING.md#how-to-contribute-code

use Exception; // a common import
use ccxt\AuthenticationError;
use React\Async;

class hollaex extends \ccxt\async\hollaex {

    use ClientTrait;

    public function describe() {
        return $this->deep_extend(parent::describe(), array(
            'has' => array(
                'ws' => true,
                'watchBalance' => true,
                'watchMyTrades' => false,
                'watchOHLCV' => false,
                'watchOrderBook' => true,
                'watchOrders' => true,
                'watchTicker' => false,
                'watchTickers' => false, // for now
                'watchTrades' => true,
            ),
            'urls' => array(
                'api' => array(
                    'ws' => 'wss://api.hollaex.com/stream',
                ),
                'test' => array(
                    'ws' => 'wss://api.sandbox.hollaex.com/stream',
                ),
            ),
            'options' => array(
                'watchBalance' => array(
                    // 'api-expires' => null,
                ),
                'watchOrders' => array(
                    // 'api-expires' => null,
                ),
            ),
            'streaming' => array(
                'ping' => array($this, 'ping'),
            ),
            'exceptions' => array(
                'ws' => array(
                    'exact' => array(
                        'Bearer or HMAC authentication required' => '\\ccxt\\BadSymbol', // array( error => 'Bearer or HMAC authentication required' )
                        'Error => wrong input' => '\\ccxt\\BadRequest', // array( error => 'Error => wrong input' )
                    ),
                ),
            ),
        ));
    }

    public function watch_order_book($symbol, $limit = null, $params = array ()) {
        return Async\async(function () use ($symbol, $limit, $params) {
            Async\await($this->load_markets());
            $market = $this->market($symbol);
            $messageHash = 'orderbook' . ':' . $market['id'];
            $orderbook = Async\await($this->watch_public($messageHash, $params));
            return $orderbook->limit ($limit);
        }) ();
    }

    public function handle_order_book($client, $message) {
        //
        //     {
        //         "topic":"orderbook",
        //         "action":"partial",
        //         "symbol":"ltc-usdt",
        //         "data":array(
        //             "bids":[
        //                 [104.29, 5.2264],
        //                 [103.86,1.3629],
        //                 [101.82,0.5942]
        //             ],
        //             "asks":[
        //                 [104.81,9.5531],
        //                 [105.54,0.6416],
        //                 [106.18,1.4141],
        //             ],
        //             "timestamp":"2022-04-12T08:17:05.932Z"
        //         ),
        //         "time":1649751425
        //     }
        //
        $marketId = $this->safe_string($message, 'symbol');
        $channel = $this->safe_string($message, 'topic');
        $market = $this->safe_market($marketId);
        $symbol = $market['symbol'];
        $data = $this->safe_value($message, 'data');
        $timestamp = $this->safe_string($data, 'timestamp');
        $timestamp = $this->parse8601($timestamp);
        $snapshot = $this->parse_order_book($data, $symbol, $timestamp);
        $orderbook = null;
        if (!(is_array($this->orderbooks) && array_key_exists($symbol, $this->orderbooks))) {
            $orderbook = $this->order_book($snapshot);
            $this->orderbooks[$symbol] = $orderbook;
        } else {
            $orderbook = $this->orderbooks[$symbol];
            $orderbook->reset ($snapshot);
        }
        $messageHash = $channel . ':' . $marketId;
        $client->resolve ($orderbook, $messageHash);
    }

    public function watch_trades($symbol, $since = null, $limit = null, $params = array ()) {
        return Async\async(function () use ($symbol, $since, $limit, $params) {
            Async\await($this->load_markets());
            $market = $this->market($symbol);
            $symbol = $market['symbol'];
            $messageHash = 'trade' . ':' . $market['id'];
            $trades = Async\await($this->watch_public($messageHash, $params));
            if ($this->newUpdates) {
                $limit = $trades->getLimit ($symbol, $limit);
            }
            return $this->filter_by_since_limit($trades, $since, $limit, 'timestamp', true);
        }) ();
    }

    public function handle_trades($client, $message) {
        //
        //     {
        //         topic => 'trade',
        //         action => 'partial',
        //         $symbol => 'btc-usdt',
        //         $data => array(
        //             array(
        //                 size => 0.05145,
        //                 price => 41977.9,
        //                 side => 'buy',
        //                 timestamp => '2022-04-11T09:40:10.881Z'
        //             ),
        //         )
        //     }
        //
        $channel = $this->safe_string($message, 'topic');
        $marketId = $this->safe_string($message, 'symbol');
        $market = $this->safe_market($marketId);
        $symbol = $market['symbol'];
        $stored = $this->safe_value($this->trades, $symbol);
        if ($stored === null) {
            $limit = $this->safe_integer($this->options, 'tradesLimit', 1000);
            $stored = new ArrayCache ($limit);
            $this->trades[$symbol] = $stored;
        }
        $data = $this->safe_value($message, 'data', array());
        $parsedTrades = $this->parse_trades($data, $market);
        for ($j = 0; $j < count($parsedTrades); $j++) {
            $stored->append ($parsedTrades[$j]);
        }
        $messageHash = $channel . ':' . $marketId;
        $client->resolve ($stored, $messageHash);
        $client->resolve ($stored, $channel);
    }

    public function watch_my_trades($symbol = null, $since = null, $limit = null, $params = array ()) {
        return Async\async(function () use ($symbol, $since, $limit, $params) {
            Async\await($this->load_markets());
            $messageHash = 'usertrade';
            $market = null;
            if ($symbol !== null) {
                $market = $this->market($symbol);
                $symbol = $market['symbol'];
                $messageHash .= ':' . $market['id'];
            }
            $trades = Async\await($this->watch_private($messageHash, $params));
            if ($this->newUpdates) {
                $limit = $trades->getLimit ($symbol, $limit);
            }
            return $this->filter_by_symbol_since_limit($trades, $symbol, $since, $limit, true);
        }) ();
    }

    public function handle_my_trades($client, $message, $subscription = null) {
        //
        // {
        //     "topic":"usertrade",
        //     "action":"insert",
        //     "user_id":"103",
        //     "symbol":"xht-usdt",
        //     "data":array(
        //        {
        //           "size":1,
        //           "side":"buy",
        //           "price":0.24,
        //           "symbol":"xht-usdt",
        //           "timestamp":"2022-05-13T09:30:15.014Z",
        //           "order_id":"6065a66e-e9a4-44a3-9726-4f8fa54b6bb6",
        //           "fee":0.001,
        //           "fee_coin":"xht",
        //           "is_same":true
        //        }
        //     ),
        //     "time":1652434215
        // }
        //
        $channel = $this->safe_string($message, 'topic');
        $rawTrades = $this->safe_value($message, 'data');
        // usually the first $message is an empty array
        // when the user does not have any trades yet
        $dataLength = count($rawTrades);
        if ($dataLength === 0) {
            return 0;
        }
        if ($this->myTrades === null) {
            $limit = $this->safe_integer($this->options, 'tradesLimit', 1000);
            $this->myTrades = new ArrayCache ($limit);
        }
        $stored = $this->myTrades;
        $marketIds = array();
        for ($i = 0; $i < count($rawTrades); $i++) {
            $trade = $rawTrades[$i];
            $parsed = $this->parse_trade($trade);
            $stored->append ($parsed);
            $symbol = $trade['symbol'];
            $market = $this->market($symbol);
            $marketId = $market['id'];
            $marketIds[$marketId] = true;
        }
        // non-$symbol specific
        $client->resolve ($this->myTrades, $channel);
        $keys = is_array($marketIds) ? array_keys($marketIds) : array();
        for ($i = 0; $i < count($keys); $i++) {
            $marketId = $keys[$i];
            $messageHash = $channel . ':' . $marketId;
            $client->resolve ($this->myTrades, $messageHash);
        }
    }

    public function watch_orders($symbol = null, $since = null, $limit = null, $params = array ()) {
        return Async\async(function () use ($symbol, $since, $limit, $params) {
            Async\await($this->load_markets());
            $messageHash = 'order';
            $market = null;
            if ($symbol !== null) {
                $market = $this->market($symbol);
                $symbol = $market['symbol'];
                $messageHash .= ':' . $market['id'];
            }
            $orders = Async\await($this->watch_private($messageHash, $params));
            if ($this->newUpdates) {
                $limit = $orders->getLimit ($symbol, $limit);
            }
            return $this->filter_by_symbol_since_limit($orders, $symbol, $since, $limit, true);
        }) ();
    }

    public function handle_order($client, $message, $subscription = null) {
        //
        //     {
        //         topic => 'order',
        //         action => 'insert',
        //         user_id => 155328,
        //         $symbol => 'ltc-usdt',
        //         $data => array(
        //             $symbol => 'ltc-usdt',
        //             side => 'buy',
        //             size => 0.05,
        //             type => 'market',
        //             price => 0,
        //             fee_structure => array( maker => 0.1, taker => 0.1 ),
        //             fee_coin => 'ltc',
        //             id => 'ce38fd48-b336-400b-812b-60c636454231',
        //             created_by => 155328,
        //             filled => 0.05,
        //             method => 'market',
        //             created_at => '2022-04-11T14:09:00.760Z',
        //             updated_at => '2022-04-11T14:09:00.760Z',
        //             status => 'filled'
        //         ),
        //         time => 1649686140
        //     }
        //
        //    {
        //        "topic":"order",
        //        "action":"partial",
        //        "user_id":155328,
        //        "data":array(
        //           {
        //              "created_at":"2022-05-13T08:19:07.694Z",
        //              "fee":0,
        //              "meta":array(
        //
        //              ),
        //              "symbol":"ltc-usdt",
        //              "side":"buy",
        //              "size":0.1,
        //              "type":"limit",
        //              "price":55,
        //              "fee_structure":array(
        //                 "maker":0.1,
        //                 "taker":0.1
        //              ),
        //              "fee_coin":"ltc",
        //              "id":"d5e77182-ad4c-4ac9-8ce4-a97f9b43e33c",
        //              "created_by":155328,
        //              "filled":0,
        //              "status":"new",
        //              "updated_at":"2022-05-13T08:19:07.694Z",
        //              "stop":null
        //           }
        //        ),
        //        "time":1652430035
        //       }
        //
        $channel = $this->safe_string($message, 'topic');
        $data = $this->safe_value($message, 'data', array());
        // usually the first $message is an empty array
        $dataLength = count($data);
        if ($dataLength === 0) {
            return 0;
        }
        if ($this->orders === null) {
            $limit = $this->safe_integer($this->options, 'ordersLimit', 1000);
            $this->orders = new ArrayCacheBySymbolById ($limit);
        }
        $stored = $this->orders;
        $rawOrders = null;
        if (gettype($data) !== 'array' || array_keys($data) !== array_keys(array_keys($data))) {
            $rawOrders = array( $data );
        } else {
            $rawOrders = $data;
        }
        $marketIds = array();
        for ($i = 0; $i < count($rawOrders); $i++) {
            $order = $rawOrders[$i];
            $parsed = $this->parse_order($order);
            $stored->append ($parsed);
            $symbol = $order['symbol'];
            $market = $this->market($symbol);
            $marketId = $market['id'];
            $marketIds[$marketId] = true;
        }
        // non-$symbol specific
        $client->resolve ($this->orders, $channel);
        $keys = is_array($marketIds) ? array_keys($marketIds) : array();
        for ($i = 0; $i < count($keys); $i++) {
            $marketId = $keys[$i];
            $messageHash = $channel . ':' . $marketId;
            $client->resolve ($this->orders, $messageHash);
        }
    }

    public function watch_balance($params = array ()) {
        return Async\async(function () use ($params) {
            $messageHash = 'wallet';
            return Async\await($this->watch_private($messageHash, $params));
        }) ();
    }

    public function handle_balance($client, $message) {
        //
        //     {
        //         topic => 'wallet',
        //         action => 'partial',
        //         user_id => 155328,
        //         $data => array(
        //             eth_balance => 0,
        //             eth_available => 0,
        //             usdt_balance => 18.94344188,
        //             usdt_available => 18.94344188,
        //             ltc_balance => 0.00005,
        //             ltc_available => 0.00005,
        //         ),
        //         time => 1649687396
        //     }
        //
        $messageHash = $this->safe_string($message, 'topic');
        $data = $this->safe_value($message, 'data');
        $keys = is_array($data) ? array_keys($data) : array();
        for ($i = 0; $i < count($keys); $i++) {
            $key = $keys[$i];
            $parts = explode('_', $key);
            $currencyId = $this->safe_string($parts, 0);
            $code = $this->safe_currency_code($currencyId);
            $account = (is_array($this->balance) && array_key_exists($code, $this->balance)) ? $this->balance[$code] : $this->account();
            $second = $this->safe_string($parts, 1);
            $freeOrTotal = ($second === 'available') ? 'free' : 'total';
            $account[$freeOrTotal] = $this->safe_string($data, $key);
            $this->balance[$code] = $account;
        }
        $this->balance = $this->safe_balance($this->balance);
        $client->resolve ($this->balance, $messageHash);
    }

    public function watch_public($messageHash, $params = array ()) {
        return Async\async(function () use ($messageHash, $params) {
            $url = $this->urls['api']['ws'];
            $request = array(
                'op' => 'subscribe',
                'args' => array( $messageHash ),
            );
            $message = array_merge($request, $params);
            return Async\await($this->watch($url, $messageHash, $message, $messageHash));
        }) ();
    }

    public function watch_private($messageHash, $params = array ()) {
        return Async\async(function () use ($messageHash, $params) {
            $this->check_required_credentials();
            $expires = $this->safe_string($this->options, 'ws-expires');
            if ($expires === null) {
                $timeout = intval($this->timeout / 1000);
                $expires = $this->sum($this->seconds(), $timeout);
                $expires = (string) $expires;
                // we need to memoize these values to avoid generating a new $url on each method execution
                // that would trigger a new connection on each received $message
                $this->options['ws-expires'] = $expires;
            }
            $url = $this->urls['api']['ws'];
            $auth = 'CONNECT' . '/stream' . $expires;
            $signature = $this->hmac($this->encode($auth), $this->encode($this->secret));
            $authParams = array(
                'api-key' => $this->apiKey,
                'api-signature' => $signature,
                'api-expires' => $expires,
            );
            $signedUrl = $url . '?' . $this->urlencode($authParams);
            $request = array(
                'op' => 'subscribe',
                'args' => array( $messageHash ),
            );
            $message = array_merge($request, $params);
            return Async\await($this->watch($signedUrl, $messageHash, $message, $messageHash));
        }) ();
    }

    public function handle_error_message($client, $message) {
        //
        //     array( $error => 'Bearer or HMAC authentication required' )
        //     array( $error => 'Error => wrong input' )
        //
        $error = $this->safe_integer($message, 'error');
        try {
            if ($error !== null) {
                $feedback = $this->id . ' ' . $this->json($message);
                $this->throw_exactly_matched_exception($this->exceptions['ws']['exact'], $error, $feedback);
            }
        } catch (Exception $e) {
            if ($e instanceof AuthenticationError) {
                return false;
            }
        }
        return $message;
    }

    public function handle_message($client, $message) {
        //
        // pong
        //
        //     array( $message => 'pong' )
        //
        // trade
        //
        //     {
        //         $topic => 'trade',
        //         action => 'partial',
        //         symbol => 'btc-usdt',
        //         data => array(
        //             array(
        //                 size => 0.05145,
        //                 price => 41977.9,
        //                 side => 'buy',
        //                 timestamp => '2022-04-11T09:40:10.881Z'
        //             ),
        //         )
        //     }
        //
        // orderbook
        //
        //     {
        //         $topic => 'orderbook',
        //         action => 'partial',
        //         symbol => 'ltc-usdt',
        //         data => array(
        //             bids => [
        //                 [104.29, 5.2264],
        //                 [103.86,1.3629],
        //                 [101.82,0.5942]
        //             ],
        //             asks => [
        //                 [104.81,9.5531],
        //                 [105.54,0.6416],
        //                 [106.18,1.4141],
        //             ],
        //             timestamp => '2022-04-11T10:37:01.227Z'
        //         ),
        //         time => 1649673421
        //     }
        //
        // order
        //
        //     {
        //         $topic => 'order',
        //         action => 'insert',
        //         user_id => 155328,
        //         symbol => 'ltc-usdt',
        //         data => array(
        //             symbol => 'ltc-usdt',
        //             side => 'buy',
        //             size => 0.05,
        //             type => 'market',
        //             price => 0,
        //             fee_structure => array( maker => 0.1, taker => 0.1 ),
        //             fee_coin => 'ltc',
        //             id => 'ce38fd48-b336-400b-812b-60c636454231',
        //             created_by => 155328,
        //             filled => 0.05,
        //             $method => 'market',
        //             created_at => '2022-04-11T14:09:00.760Z',
        //             updated_at => '2022-04-11T14:09:00.760Z',
        //             status => 'filled'
        //         ),
        //         time => 1649686140
        //     }
        //
        // balance
        //
        //     {
        //         $topic => 'wallet',
        //         action => 'partial',
        //         user_id => 155328,
        //         data => {
        //             eth_balance => 0,
        //             eth_available => 0,
        //             usdt_balance => 18.94344188,
        //             usdt_available => 18.94344188,
        //             ltc_balance => 0.00005,
        //             ltc_available => 0.00005,
        //         }
        //     }
        //
        if (!$this->handle_error_message($client, $message)) {
            return;
        }
        $content = $this->safe_string($message, 'message');
        if ($content === 'pong') {
            $this->handle_pong($client, $message);
            return;
        }
        $methods = array(
            'trade' => array($this, 'handle_trades'),
            'orderbook' => array($this, 'handle_order_book'),
            'order' => array($this, 'handle_order'),
            'wallet' => array($this, 'handle_balance'),
            'usertrade' => array($this, 'handle_my_trades'),
        );
        $topic = $this->safe_value($message, 'topic');
        $method = $this->safe_value($methods, $topic);
        if ($method !== null) {
            $method($client, $message);
        }
    }

    public function ping($client) {
        // hollaex does not support built-in ws protocol-level ping-pong
        return array( 'op' => 'ping' );
    }

    public function handle_pong($client, $message) {
        $client->lastPong = $this->milliseconds();
        return $message;
    }

    public function on_error($client, $error) {
        $this->options['ws-expires'] = null;
        parent::on_error($client, $error);
    }

    public function on_close($client, $error) {
        $this->options['ws-expires'] = null;
        parent::on_close($client, $error);
    }
}
