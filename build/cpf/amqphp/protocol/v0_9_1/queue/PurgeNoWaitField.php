<?php
 namespace amqphp\protocol\v0_9_1\queue; class PurgeNoWaitField extends \amqphp\protocol\v0_9_1\NoWaitDomain implements \amqphp\protocol\abstrakt\XmlSpecField { function getSpecFieldName() { return 'no-wait'; } function getSpecFieldDomain() { return 'no-wait'; } }