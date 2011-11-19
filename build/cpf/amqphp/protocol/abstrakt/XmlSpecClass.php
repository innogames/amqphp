<?php
 namespace amqphp\protocol\abstrakt; abstract class XmlSpecClass { protected $name; protected $index; protected $fields; protected $methods; protected $methFact; protected $fieldFact; final function getSpecName () { return $this->name; } final function getSpecIndex () { return $this->index; } final function getSpecFields () { return $this->fields; } final function getSpecMethods () { return $this->methods; } final function getMethods () { return call_user_func(array($this->methFact, 'GetMethodsByName'), $this->methods); } final function getMethodByName ($mName) { if (in_array($mName, $this->methods)) { return call_user_func(array($this->methFact, 'GetMethodByName'), $mName); } } final function getMethodByIndex ($idx) { if (in_array($idx, array_keys($this->methods))) { return call_user_func(array($this->methFact, 'GetMethodByIndex'), $idx); } } final function getFields () { return call_user_func(array($this->fieldFact, 'GetClassFields'), $this->name); } final function getFieldByName ($fName) { if (in_array($fName, $this->fields)) { return call_user_func(array($this->fieldFact, 'GetField'), $fName); } } } 