<?php

/**
 * @package Wsdl2PhpGenerator
 */

/**
 * @see PhpClass
 */
require_once dirname(__FILE__).'/../lib/phpSource/PhpClass.php';

/**
 * @see PhpDocElementFactory.php
 */
require_once dirname(__FILE__).'/../lib/phpSource/PhpDocElementFactory.php';

/**
 * @see Operation
 */
require_once dirname(__FILE__).'/Operation.php';

/**
 * Service represents the service in the wsdl
 *
 * @package Wsdl2PhpGenerator
 * @author Fredrik Wallgren <fredrik.wallgren@gmail.com>
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */
class Service
{
  /**
   *
   * @var PhpClass The class used to create the service.
   */
  private $class;

  /**
   *
   * @var string The name of the service
   */
  private $identifier;

  /**
   *
   * @var array[Operation] An array containing the operations of the service
   */
  private $operations;

  /**
   *
   * @var string The description of the service used as description in the phpdoc of the class
   */
  private $description;

  /**
   *
   * @var array An array of Types
   */
  private $types;

  /**
   *
   * @param string $identifier The name of the service
   * @param array $types The types the service knows about
   * @param string $description The description of the service
   */
  function __construct($identifier, array $types, $description)
  {
    $this->identifier = $identifier;
    $this->types = $types;
    $this->description = $description;
  }

  /**
   *
   * @return PhpClass Returns the class, generates it if not done
   */
  public function getClass()
  {
    if($this->class == null)
    {
      $this->generateClass();
    }

    return $this->class;
  }

  /**
   * Generates the class if not already generated
   */
  public function generateClass()
  {
    $config = Generator::getInstance()->getConfig();

    if($config->getServiceClassName())
    {
      $name = $config->getServiceClassName();
    }
    else
    {
      // Generate a valid classname
      try
      {
        $name = Validator::validateClass($this->identifier);
      }
      catch (ValidationException $e)
      {
        $name .= 'Custom';
      }
    }

    // Create the class object
    $comment = new PhpDocComment($this->description);
    $this->class = new PhpClass($name, $config->getClassExists(), 'SoapClient', $comment);

    // Create the constructor
    $comment = new PhpDocComment();
    $comment->addParam(PhpDocElementFactory::getParam('array', 'options', 'A array of config values'));
    $comment->addParam(PhpDocElementFactory::getParam('string', 'wsdl', 'The wsdl file to use'));

    $source = '  foreach(self::$classmap as $key => $value)
  {
    if(!isset($options[\'classmap\'][$key]))
    {
      $options[\'classmap\'][$key] = $value;
    }
  }
  '.$this->generateServiceOptions($config).'
  parent::__construct($wsdl, $options);'.PHP_EOL;

    $function = new PhpFunction('public', '__construct', 'array $options = array(), $wsdl = \''.$config->getInputFile().'\'', $source, $comment);

    // Add the constructor
    $this->class->addFunction($function);

    // Generate the classmap
    $name = 'classmap';
    $comment = new PhpDocComment();
    $comment->setVar(PhpDocElementFactory::getVar('array', $name, 'The defined classes'));

    $init = 'array('.PHP_EOL;
    foreach ($this->types as $type)
    {
      if($type instanceof ComplexType)
      {
        if($type->getMemberCount())
        {
          $init .= "  '".$type->getIdentifier()."' => '".$type->getPhpIdentifier()."',".PHP_EOL;
        }
      }
    }
    $init = substr($init, 0, strrpos($init, ','));
    $init .= ')';
    $var = new PhpVariable('private static', $name, $init, $comment);

    // Add the classmap variable
    $this->class->addVariable($var);

    // Add all methods
    foreach ($this->operations as $operation)
    {
      $name = Validator::validateNamingConvention($operation->getName());

      $comment = new PhpDocComment($operation->getDescription());

      $params = $operation->getParams();

      $returns = Validator::validateType($operation->getReturns());

      foreach ($params as $param => $hint)
      {
        $arr = $operation->getPhpDocParams($param, $this->types);
        if($arr['params'] !== 0)
        {
          $comment->addParam(PhpDocElementFactory::getParam($arr['type'], $param, $arr['desc']));
        }
      }

      $comment->setReturn(PhpDocElementFactory::getReturn($returns, ''));

      if(!count($params))
      {
        $source = '  return $this->__soapCall(\''.$name.'\', array());' . PHP_EOL;
        $paramStr = '';
      }
      else
      {
        $source = '  return $this->__soapCall(\''.$name.'\', array(' . implode(', ', array_keys($params)) . '));' . PHP_EOL;
        $paramStr = $operation->getParamString($this->types);
      }

      $function = new PhpFunction('public', $name, $paramStr, $source, $comment);

      if ($this->class->functionExists($function->getIdentifier()) == false)
      {
        $this->class->addFunction($function);
      }
    }
  }

  /**
   * Adds an operation to the service
   *
   * @param string $name
   * @param array $params
   * @param string $description
   */
  public function addOperation($name, $params, $description, $returns)
  {
    $this->operations[] = new Operation($name, $params, $description, $returns);
  }

  /**
   *
   * @param Config $config The config containing the values to use
   *
   * @return string Returns the string for the options array
   */
  private function generateServiceOptions(Config $config)
  {
    $ret = '';

    if (count($config->getOptionFeatures()) > 0)
    {
      $i = 0;
      $ret .= "
  if (isset(\$options['features']) == false)
  {
    \$options['features'] = ";
      foreach ($config->getOptionFeatures() as $option)
      {
        if ($i++ > 0)
        {
          $ret .= ' | ';
        }

        $ret .= $option;
      }

      $ret .= ";
  }".PHP_EOL;
    }

    if (strlen($config->getWsdlCache()) > 0)
    {
      $ret .= "
  if (isset(\$options['wsdl_cache']) == false)
  {
    \$options['wsdl_cache'] = ".$config->getWsdlCache();
      $ret .= ";
  }".PHP_EOL;
    }

    if (strlen($config->getCompression()) > 0)
    {
      $ret .= "
  if (isset(\$options['compression']) == false)
  {
    \$options['compression'] = ".$config->getCompression();
      $ret .= ";
  }".PHP_EOL;
    }

    return $ret;
  }
}

