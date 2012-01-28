<?php
 namespace amqphp\wire; use amqphp\protocol as proto; use amqphp\protocol\abstrakt; abstract class Protocol { private static $Versions = array('0.9.1'); private static $ImplTypes = array('Table', 'Boolean', 'ShortShortInt', 'ShortShortUInt', 'ShortInt', 'ShortUInt', 'LongInt', 'LongUInt', 'LongLongInt', 'LongLongUInt', 'Float', 'Double', 'DecimalValue', 'ShortString', 'LongString', 'FieldArray', 'Timestamp'); private static $XmlTypesMap = array('bit' => 'Boolean', 'octet' => 'ShortShortUInt', 'short' => 'ShortUInt', 'long' => 'LongUInt', 'longlong' => 'LongLongUInt', 'shortstr' => 'ShortString', 'longstr' => 'LongString', 'timestamp' => 'LongLongUInt', 'table' => 'Table'); private static $AmqpTableMap = array('t' => 'ShortShortUInt', 'b' => 'ShortShortInt', 'B' => 'ShortShortUInt', 'U' => 'ShortInt', 'u' => 'ShortUInt', 'I' => 'LongInt', 'i' => 'LongUInt', 'L' => 'LongLongInt', 'l' => 'LongLongUInt', 'f' => 'Float', 'd' => 'Double', 'D' => 'DecimalValue', 's' => 'ShortString', 'S' => 'LongString', 'A' => 'FieldArray', 'T' => 'LongLongUInt', 'F' => 'Table'); protected $bin; static function GetXmlTypes () { return self::$XmlTypesMap; } protected function getImplForXmlType($t) { return isset(self::$XmlTypesMap[$t]) ? self::$XmlTypesMap[$t] : null; } protected function getImplForTableType($t) { return isset(self::$AmqpTableMap[$t]) ? self::$AmqpTableMap[$t] : null; } protected function getTableTypeForValue($val) { if (is_bool($val)) { return 't'; } else if (is_int($val)) { if ($val > 0) { if ($val < 256) { return 'B'; } else if ($val < 65536) { return 'u'; } else if ($val < 4294967296) { return 'i'; } else { return 'l'; } } else if ($val < 0) { $val = abs($val); if ($val < 256) { return 'b'; } else if ($val < 65536) { return 'U'; } else if ($val < 4294967296) { return 'I'; } else { return 'L'; } } else { return 'B'; } } else if (is_float($val)) { return 'f'; } else if (is_string($val)) { return 'S'; } else if (is_array($val)) { $isArray = false; foreach (array_keys($val) as $k) { if (is_int($k)) { $isArray = true; break; } } return $isArray ? 'A' : 'F'; } else if ($val instanceof Decimal) { return 'D'; } return null; } protected function getXmlTypeForValue($val) { if (is_bool($val)) { return 'bit'; } else if (is_int($val)) { $val = abs($val); if ($val < 256) { return 'octet'; } else if ($val < 65536) { return 'short'; } else if ($val < 4294967296) { return 'long'; } else { return 'longlong'; } } else if (is_string($val)) { return (strlen($val) < 255) ? 'shortstr' : 'longstr'; } else if (is_array($val) || $val instanceof Table) { return 'table'; } return null; } function getBuffer() { return $this->bin; } } 