<?php
 namespace amqphp; use amqphp\protocol; use amqphp\wire; const DEBUG = false; const PROTOCOL_HEADER = "AMQP\x00\x00\x09\x01"; const SELECT_TIMEOUT_ABS = 1; const SELECT_TIMEOUT_REL = 2; const SELECT_MAXLOOPS = 3; const SELECT_CALLBACK = 4; const SELECT_COND = 5; const SELECT_INFINITE = 6; const CONSUMER_ACK = 1; const CONSUMER_REJECT = 2; const CONSUMER_DROP = 3; const CONSUMER_CANCEL = 4; class Connection { const SELECT_TIMEOUT_ABS = SELECT_TIMEOUT_ABS; const SELECT_TIMEOUT_REL = SELECT_TIMEOUT_REL; const SELECT_MAXLOOPS = SELECT_MAXLOOPS; const SELECT_CALLBACK = SELECT_CALLBACK; const SELECT_COND = SELECT_COND; const SELECT_INFINITE = SELECT_INFINITE; private static $ClientProperties = array( 'product' => ' BraveSirRobin/amqphp', 'version' => '0.9-beta', 'platform' => 'PHP 5.3 +', 'copyright' => 'Copyright (c) 2010,2011 Robin Harvey (harvey.robin@gmail.com)', 'information' => 'This software is released under the terms of the GNU LGPL: http://www.gnu.org/licenses/lgpl-3.0.txt'); public $capabilities; private static $CProps = array( 'socketImpl', 'socketParams', 'username', 'userpass', 'vhost', 'frameMax', 'chanMax', 'signalDispatch', 'heartbeat'); private $sock; private $socketImpl = '\amqphp\Socket'; private $protoImpl = 'v0_9_1'; private $protoLoader; private $socketParams = array('host' => 'localhost', 'port' => 5672); private $username; private $userpass; private $vhost; private $frameMax = 65536; private $chanMax = 50; private $heartbeat = 0; private $signalDispatch = true; private $chans = array(); private $nextChan = 1; private $blocking = false; private $unDelivered = array(); private $unDeliverable = array(); private $incompleteMethods = array(); private $readSrc = null; private $connected = false; private $slHelper; function __construct (array $params = array()) { $this->setConnectionParams($params); $this->setSelectMode(SELECT_COND); } function setConnectionParams (array $params) { foreach (self::$CProps as $pn) { if (isset($params[$pn])) { $this->$pn = $params[$pn]; } } } function getProtocolLoader () { if (is_null($this->protoLoader)) { $protoImpl = $this->protoImpl; $this->protoLoader = function ($class, $method, $args) use ($protoImpl) { $fqClass = '\\amqphp\\protocol\\' . $protoImpl . '\\' . $class; return call_user_func_array(array($fqClass, $method), $args); }; } return $this->protoLoader; } function shutdown () { if (! $this->connected) { trigger_error("Cannot shut a closed connection", E_USER_WARNING); return; } foreach (array_keys($this->chans) as $cName) { $this->chans[$cName]->shutdown(); } $pl = $this->getProtocolLoader(); $meth = new wire\Method($pl('ClassFactory', 'GetMethod', array('connection', 'close'))); $meth->setField('reply-code', ''); $meth->setField('reply-text', ''); $meth->setField('class-id', ''); $meth->setField('method-id', ''); if (! $this->write($meth->toBin($this->getProtocolLoader()))) { trigger_error("Unclean connection shutdown (1)", E_USER_WARNING); return; } if (! ($raw = $this->read())) { trigger_error("Unclean connection shutdown (2)", E_USER_WARNING); return; } $meth = new wire\Method(); $meth->readConstruct(new wire\Reader($raw), $this->getProtocolLoader()); if (! ($meth->getClassProto() && $meth->getClassProto()->getSpecName() == 'connection' && $meth->getMethodProto() && $meth->getMethodProto()->getSpecName() == 'close-ok')) { trigger_error("Channel protocol shudown fault", E_USER_WARNING); } $this->sock->close(); $this->connected = false; } private function initSocket () { if (! isset($this->socketImpl)) { throw new \Exception("No socket implementation specified", 7545); } $this->sock = new $this->socketImpl($this->socketParams); } function connect (array $params = array()) { if ($this->connected) { trigger_error("Connection is connected already", E_USER_WARNING); return; } $this->setConnectionParams($params); $this->initSocket(); $this->sock->connect(); if (! $this->write(PROTOCOL_HEADER)) { throw new \Exception("Connection initialisation failed (1)", 9873); } if (! ($raw = $this->read())) { throw new \Exception("Connection initialisation failed (2)", 9874); } if (substr($raw, 0, 4) == 'AMQP' && $raw !== PROTOCOL_HEADER) { throw new \Exception("Connection initialisation failed (3)", 9875); } $meth = new wire\Method(); $meth->readConstruct(new wire\Reader($raw), $this->getProtocolLoader()); if (($startF = $meth->getField('server-properties')) && isset($startF['capabilities']) && ($startF['capabilities']->getType() == 'F')) { $this->capabilities = $startF['capabilities']->getValue()->getArrayCopy(); } if ($meth->getMethodProto()->getSpecIndex() == 10 && $meth->getClassProto()->getSpecIndex() == 10) { $resp = $meth->getMethodProto()->getResponses(); $meth = new wire\Method($resp[0]); } else { throw new \Exception("Connection initialisation failed (5)", 9877); } $meth->setField('client-properties', $this->getClientProperties()); $meth->setField('mechanism', 'AMQPLAIN'); $meth->setField('response', $this->getSaslResponse()); $meth->setField('locale', 'en_US'); if (! ($this->write($meth->toBin($this->getProtocolLoader())))) { throw new \Exception("Connection initialisation failed (6)", 9878); } if (! ($raw = $this->read())) { throw new \Exception("Connection initialisation failed (7)", 9879); } $meth = new wire\Method(); $meth->readConstruct(new wire\Reader($raw), $this->getProtocolLoader()); $chanMax = $meth->getField('channel-max'); $frameMax = $meth->getField('frame-max'); $this->chanMax = ($chanMax < $this->chanMax) ? $chanMax : $this->chanMax; $this->frameMax = ($this->frameMax == 0 || $frameMax < $this->frameMax) ? $frameMax : $this->frameMax; if ($meth->getMethodProto()->getSpecIndex() == 30 && $meth->getClassProto()->getSpecIndex() == 10) { $resp = $meth->getMethodProto()->getResponses(); $meth = new wire\Method($resp[0]); } else { throw new \Exception("Connection initialisation failed (9)", 9881); } $meth->setField('channel-max', $this->chanMax); $meth->setField('frame-max', $this->frameMax); $meth->setField('heartbeat', $this->heartbeat); if (! ($this->write($meth->toBin($this->getProtocolLoader())))) { throw new \Exception("Connection initialisation failed (10)", 9882); } $meth = $this->constructMethod('connection', array('open', array('virtual-host' => $this->vhost))); $meth = $this->invoke($meth); if (! $meth || ! ($meth->getMethodProto()->getSpecIndex() == 41 && $meth->getClassProto()->getSpecIndex() == 10)) { throw new \Exception("Connection initialisation failed (13)", 9885); } $this->connected = true; } private function getClientProperties () { $t = new wire\Table; foreach (self::$ClientProperties as $pn => $pv) { $t[$pn] = new wire\TableField($pv, 'S'); } return $t; } private function getSaslResponse () { $t = new wire\Table(); $t['LOGIN'] = new wire\TableField($this->username, 'S'); $t['PASSWORD'] = new wire\TableField($this->userpass, 'S'); $w = new wire\Writer(); $w->write($t, 'table'); return substr($w->getBuffer(), 4); } function getChannel ($num = false) { return ($num === false) ? $this->initNewChannel() : $this->chans[$num]; } function getChannels () { return $this->chans; } function setSignalDispatch ($val) { $this->signalDispatch = (boolean) $val; } function removeChannel (Channel $chan) { if (false !== ($k = array_search($chan, $this->chans))) { unset($this->chans[$k]); } else { trigger_error("Channel not found", E_USER_WARNING); } } function getSocketId () { return $this->sock->getId(); } private function initNewChannel () { if (! $this->connected) { trigger_error("Connection is not connected - cannot create Channel", E_USER_WARNING); return null; } $newChan = $this->nextChan++; if ($this->chanMax > 0 && $newChan > $this->chanMax) { throw new \Exception("Channels are exhausted!", 23756); } $this->chans[$newChan] = new Channel($this, $newChan, $this->frameMax); $this->chans[$newChan]->initChannel(); return $this->chans[$newChan]; } function getVHost () { return $this->vhost; } function getSocketImplClass () { return $this->socketImpl; } function isConnected () { return $this->connected; } private function read () { $ret = $this->sock->read(); if ($ret === false) { $errNo = $this->sock->lastError(); if ($this->signalDispatch && $this->sock->selectInterrupted()) { pcntl_signal_dispatch(); } $errStr = $this->sock->strError(); throw new \Exception ("[1] Read block select produced an error: [$errNo] $errStr", 9963); } return $ret; } private function write ($buffs) { $bw = 0; foreach ((array) $buffs as $buff) { $bw += $this->sock->write($buff); } return $bw; } private function handleConnectionMessage (wire\Method $meth) { if ($meth->isHeartbeat()) { $resp = "\x08\x00\x00\x00\x00\x00\x00\xce"; $this->write($resp); return; } $clsMth = "{$meth->getClassProto()->getSpecName()}.{$meth->getMethodProto()->getSpecName()}"; switch ($clsMth) { case 'connection.close': $pl = $this->getProtocolLoader(); if ($culprit = $pl('ClassFactory', 'GetMethod', array($meth->getField('class-id'), $meth->getField('method-id')))) { $culprit = "{$culprit->getSpecClass()}.{$culprit->getSpecName()}"; } else { $culprit = '(Unknown or unspecified)'; } $errCode = $pl('ProtoConsts', 'Konstant', array($meth->getField('reply-code'))); $eb = ''; foreach ($meth->getFields() as $k => $v) { $eb .= sprintf("(%s=%s) ", $k, $v); } $tmp = $meth->getMethodProto()->getResponses(); $closeOk = new wire\Method($tmp[0]); $em = "[connection.close] reply-code={$errCode['name']} triggered by $culprit: $eb"; if ($this->write($closeOk->toBin($this->getProtocolLoader()))) { $em .= " Connection closed OK"; $n = 7565; } else { $em .= " Additionally, connection closure ack send failed"; $n = 7566; } $this->sock->close(); throw new \Exception($em, $n); default: $this->sock->close(); throw new \Exception(sprintf("Unexpected channel message (%s.%s), connection closed", $meth->getClassProto()->getSpecName(), $meth->getMethodProto()->getSpecName()), 96356); } } function isBlocking () { return $this->blocking; } function setBlocking ($b) { $this->blocking = (boolean) $b; } function select () { $evl = new EventLoop; $evl->addConnection($this); $evl->select(); } function setSelectMode () { if ($this->blocking) { trigger_error("Select mode - cannot switch mode whilst blocking", E_USER_WARNING); return false; } $_args = func_get_args(); if (! $_args) { trigger_error("Select mode - no select parameters supplied", E_USER_WARNING); return false; } switch ($mode = array_shift($_args)) { case SELECT_TIMEOUT_ABS: case SELECT_TIMEOUT_REL: @list($epoch, $usecs) = $_args; $this->slHelper = new TimeoutSelectHelper; return $this->slHelper->configure($mode, $epoch, $usecs); case SELECT_MAXLOOPS: $this->slHelper = new MaxloopSelectHelper; return $this->slHelper->configure(SELECT_MAXLOOPS, array_shift($_args)); case SELECT_CALLBACK: $cb = array_shift($_args); $this->slHelper = new CallbackSelectHelper; return $this->slHelper->configure(SELECT_CALLBACK, $cb, $_args); case SELECT_COND: $this->slHelper = new ConditionalSelectHelper; return $this->slHelper->configure(SELECT_COND, $this); case SELECT_INFINITE: $this->slHelper = new InfiniteSelectHelper; return $this->slHelper->configure(SELECT_INFINITE); default: trigger_error("Select mode - mode not found", E_USER_WARNING); return false; } } function notifyPreSelect () { return $this->slHelper->preSelect(); } function notifySelectInit () { $this->slHelper->init($this); foreach ($this->chans as $chan) { $chan->onSelectStart(); } } function notifyComplete () { $this->slHelper->complete(); } function doSelectRead () { $buff = $this->sock->readAll(); if ($buff && ($meths = $this->readMessages($buff))) { $this->unDelivered = array_merge($this->unDelivered, $meths); } else if ($buff == '') { $this->blocking = false; throw new \Exception("Empty read in blocking select loop : " . strlen($buff), 9864); } } function invoke (wire\Method $inMeth, $noWait=false) { if (! ($this->write($inMeth->toBin($this->getProtocolLoader())))) { throw new \Exception("Send message failed (1)", 5623); } if (! $noWait && $inMeth->getMethodProto()->getSpecResponseMethods()) { if ($inMeth->getMethodProto()->hasNoWaitField()) { foreach ($inMeth->getMethodProto()->getFields() as $f) { if ($f->getSpecDomainName() == 'no-wait' && $inMeth->getField($f->getSpecFieldName())) { return; } } } while (true) { if (! ($buff = $this->read())) { throw new \Exception(sprintf("(2) Send message failed for %s.%s:\n", $inMeth->getClassProto()->getSpecName(), $inMeth->getMethodProto()->getSpecName()), 5624); } $meths = $this->readMessages($buff); foreach (array_keys($meths) as $k) { $meth = $meths[$k]; unset($meths[$k]); if ($inMeth->isResponse($meth)) { if ($meths) { $this->unDelivered = array_merge($this->unDelivered, $meths); } return $meth; } else { $this->unDelivered[] = $meth; } } } } } private function readMessages ($buff) { if (is_null($this->readSrc)) { $src = new wire\Reader($buff); } else { $src = $this->readSrc; $src->append($buff); $this->readSrc = null; } $allMeths = array(); while (true) { $meth = null; if ($this->incompleteMethods) { foreach ($this->incompleteMethods as $im) { if ($im->canReadFrom($src)) { $meth = $im; $rcr = $meth->readConstruct($src, $this->getProtocolLoader()); break; } } } if (! $meth) { $meth = new wire\Method; $this->incompleteMethods[] = $meth; $rcr = $meth->readConstruct($src, $this->getProtocolLoader()); } if ($meth->readConstructComplete()) { if (false !== ($p = array_search($meth, $this->incompleteMethods, true))) { unset($this->incompleteMethods[$p]); } if ($this->connected && $meth->getWireChannel() == 0) { $this->handleConnectionMessage($meth); } else if ($meth->getWireClassId() == 20 && ($chan = $this->chans[$meth->getWireChannel()])) { $chanR = $chan->handleChannelMessage($meth); if ($chanR === true) { $allMeths[] = $meth; } } else { $allMeths[] = $meth; } } if ($rcr === wire\Method::PARTIAL_FRAME) { $this->readSrc = $src; break; } else if ($src->isSpent()) { break; } } return $allMeths; } function getUndeliveredMessages () { return $this->unDelivered; } function deliverAll () { while ($this->unDelivered) { $meth = array_shift($this->unDelivered); if (isset($this->chans[$meth->getWireChannel()])) { $this->chans[$meth->getWireChannel()]->handleChannelDelivery($meth); } else { trigger_error("Message delivered on unknown channel", E_USER_WARNING); $this->unDeliverable[] = $meth; } } } function getUndeliverableMessages ($chan) { $r = array(); foreach (array_keys($this->unDeliverable) as $k) { if ($this->unDeliverable[$k]->getWireChannel() == $chan) { $r[] = $this->unDeliverable[$k]; } } return $r; } function removeUndeliverableMessages ($chan) { foreach (array_keys($this->unDeliverable) as $k) { if ($this->unDeliverable[$k]->getWireChannel() == $chan) { unset($this->unDeliverable[$k]); } } } function constructMethod ($class, $_args) { $method = (isset($_args[0])) ? $_args[0] : null; $args = (isset($_args[1])) ? $_args[1] : array(); $content = (isset($_args[2])) ? $_args[2] : null; $pl = $this->getProtocolLoader(); if (! ($cls = $pl('ClassFactory', 'GetClassByName', array($class)))) { throw new \Exception("Invalid Amqp class or php method", 8691); } else if (! ($meth = $cls->getMethodByName($method))) { throw new \Exception("Invalid Amqp method", 5435); } $m = new wire\Method($meth); $clsF = $cls->getSpecFields(); $mthF = $meth->getSpecFields(); if ($meth->getSpecHasContent() && $clsF) { foreach (array_merge(array_combine($clsF, array_fill(0, count($clsF), null)), $args) as $k => $v) { $m->setClassField($k, $v); } } if ($mthF) { foreach (array_merge(array_combine($mthF, array_fill(0, count($mthF), '')), $args) as $k => $v) { $m->setField($k, $v); } } $m->setContent($content); return $m; } } 