<?php
 namespace amqphp\protocol\v0_9_1\basic; class GetOkDeliveryTagField extends \amqphp\protocol\v0_9_1\DeliveryTagDomain implements \amqphp\protocol\abstrakt\XmlSpecField { function getSpecFieldName() { return 'delivery-tag'; } function getSpecFieldDomain() { return 'delivery-tag'; } }