<?php
namespace amqphp\protocol\v0_9_1\basic; abstract class MethodFactory extends \amqphp\protocol\abstrakt\MethodFactory { protected static $Cache = array(array(10, 'qos', '\\amqphp\\protocol\\v0_9_1\\basic\\QosMethod'),array(11, 'qos-ok', '\\amqphp\\protocol\\v0_9_1\\basic\\QosOkMethod'),array(20, 'consume', '\\amqphp\\protocol\\v0_9_1\\basic\\ConsumeMethod'),array(21, 'consume-ok', '\\amqphp\\protocol\\v0_9_1\\basic\\ConsumeOkMethod'),array(30, 'cancel', '\\amqphp\\protocol\\v0_9_1\\basic\\CancelMethod'),array(31, 'cancel-ok', '\\amqphp\\protocol\\v0_9_1\\basic\\CancelOkMethod'),array(40, 'publish', '\\amqphp\\protocol\\v0_9_1\\basic\\PublishMethod'),array(50, 'return', '\\amqphp\\protocol\\v0_9_1\\basic\\ReturnMethod'),array(60, 'deliver', '\\amqphp\\protocol\\v0_9_1\\basic\\DeliverMethod'),array(70, 'get', '\\amqphp\\protocol\\v0_9_1\\basic\\GetMethod'),array(71, 'get-ok', '\\amqphp\\protocol\\v0_9_1\\basic\\GetOkMethod'),array(72, 'get-empty', '\\amqphp\\protocol\\v0_9_1\\basic\\GetEmptyMethod'),array(80, 'ack', '\\amqphp\\protocol\\v0_9_1\\basic\\AckMethod'),array(90, 'reject', '\\amqphp\\protocol\\v0_9_1\\basic\\RejectMethod'),array(100, 'recover-async', '\\amqphp\\protocol\\v0_9_1\\basic\\RecoverAsyncMethod'),array(110, 'recover', '\\amqphp\\protocol\\v0_9_1\\basic\\RecoverMethod'),array(111, 'recover-ok', '\\amqphp\\protocol\\v0_9_1\\basic\\RecoverOkMethod'),array(120, 'nack', '\\amqphp\\protocol\\v0_9_1\\basic\\NackMethod')); }