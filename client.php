#!/usr/bin/env php
<?php
if (!extension_loaded('msgpack')) {
  die('msgpack extension missing');
}

if (!extension_loaded('zmq')) {
  die('zmq extension missing');
}
/**
 * Sample implementation of of PHP Cypher-over-ZMQ Client
 */
class Client
{

  /**
   * @var ZMQContext
   */
  protected $context;

  /**
   * @var ZMQSocket
   */
  protected $socket;

  public function __construct()
  {
    $this->context = new ZMQContext();
  }

  /**
   * @throws Exception
   */
  protected function openSocket()
  {
    try {
      $this->socket = new ZMQSocket($this->context, ZMQ::SOCKET_REQ);
      $this->socket->connect('tcp://localhost:5555');
      $this->socket->setSockOpt(ZMQ::SOCKOPT_LINGER, false);
    }
    catch (ZMQException $e) {
      throw new Exception(sprintf('[%s] Failed to connect REQ socket. Reason: "%s"', __METHOD__, $e->getMessage()));
    }
  }

  /**
   * @param  string $query
   * @param  array  $opts
   * @return array
   * @throws Exception
   */
  public function query($query, $opts = array())
  {
    $default_opts = array(
      'query' => $query,
      'stats' => true,
      'params' => array(
        'id' => 0
      )
    );
    $msg = array_merge($default_opts, $opts);

    try {
      $packed_msg = msgpack_pack($msg);

      $this->openSocket();
      $this->socket->send($packed_msg);
      $res = $this->socket->recv();
      $unpacked_res = msgpack_unpack($res);
    }
    catch (ZMQException $e) {
      throw new Exception(sprintf('[%s] Operation failed. Reason: "%s" Code: %d', __METHOD__, $e->getMessage(), $e->getCode()));
    }

    return $unpacked_res;
  }

}

$outer_loops = 10;
$inner_loops = 1000;
$total_loops = $outer_loops * $inner_loops;


// test client w/o tx
$start = microtime(true);
try {
  $client = new Client();

  for ($i = 0; $i < $outer_loops; $i++) {
    for ($j = 0; $j < $inner_loops; $j++) {
      $client->query('create n={name:{name}}', array('params' => array('name' => 'test' . $j)));
    }
  }
}
catch (Exception $e) {
  echo sprintf('Error occured while executing the client. Reason: "%s"', $e->getMessage()) . "\n";
}

$stop = microtime(true);
$delta = $stop - $start;

echo sprintf("%d queries w/o tx took %f s\n", $total_loops, $delta);

// test client w/ tx
$start = microtime(true);
try {
  $client = new Client();

  for ($i = 0; $i < $outer_loops; $i++) {
    // open tx
    $res = $client->query(null, array('tx' => 'begin', 'stats' => true));
    for ($j = 0; $j < $inner_loops; $j++) {
      $client->query('create n={name:{name}}', array('tx_id' => $res['tx_id'], 'params' => array('name' => 'test' . $j)));
    }
    // commit tx
    $client->query(null, array('tx' => 'commit', 'tx_id' => $res['tx_id']));
  }
}
catch (Exception $e) {
  echo sprintf('Error occured while executing the client. Reason: "%s"', $e->getMessage()) . "\n";
}

$stop = microtime(true);
$delta = $stop - $start;

echo sprintf("%d queries w/ tx took %f s\n", $total_loops, $delta);
