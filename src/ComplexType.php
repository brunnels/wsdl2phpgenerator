<?php

/**
 * @package Generator
 */

/**
 * @see Type
 */
require_once dirname(__FILE__).'/Type.php';

/**
 * @see Variable
 */
require_once dirname(__FILE__).'/Variable.php';

/**
 * ComplexType
 *
 * @package Wsdl2PhpGenerator
 * @author Fredrik Wallgren <fredrik.wallgren@gmail.com>
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */
class ComplexType extends Type
{
  /**
   *
   * @var array The members in the type
   */
  private $members = array();

  /**
   *
   * @var array The primitive types
   */
  private $primitives = array(
    'int',
    'float',
    'string',
    'bool',
    'mixed'
  );

  /**
   * Construct the object
   *
   * @param string $name The identifier for the class
   * @param string $restriction The restriction(datatype) of the values
   */
  function __construct($name)
  {
    parent::__construct($name, null);
  }
  
  private function generateParameterConstructor($class)
  {
    $constructorComment = new PhpDocComment();

    $constructorSource  = '';
    $constructorParameters = '';

    $i = 0;
    foreach ($this->members as $member)
    {
      $type = '';

      try
      {
        $type = Validator::validateType($member->getType());
      }
      catch (ValidationException $e)
      {
        $type .= 'Custom';
      }

      $name = str_replace('__', '', Validator::validateNamingConvention($member->getName()));
      $constructorSource .= "  call_user_func(array(\$this, 'set" . ucfirst($this->camelize($name)) . "'), $$name);".PHP_EOL;
      $constructorComment->addParam(PhpDocElementFactory::getParam($type, $name, ''));
      $constructorParameters .= ((!in_array($type, $this->primitives)) ? ", $type " : ', ') . "$$name = NULL";
      $i++;
    }

    $constructorParameters = substr($constructorParameters, 2); // Remove first comma

    $constructorFunction = new PhpFunction('public', '__construct', $constructorParameters, $constructorSource, $constructorComment);
    $class->addFunction($constructorFunction);
    return $class;
  }

  private function generateArrayParameterConstructor($class)
  {
    $constructorComment = new PhpDocComment();

    $constructorSource  = '';
    $constructorParameters = '';
    $propertiesComment = "Properties array\n *\n * Form the \$properties array like this:\n * <code>\n * \$properties = arrray(\n";

    foreach ($this->members as $member)
    {
      $type = '';

      try
      {
        $type = Validator::validateType($member->getType());
      }
      catch (ValidationException $e)
      {
        $type .= 'Custom';
      }

      $name = str_replace('__', '', Validator::validateNamingConvention($member->getName()));

      $propertiesComment .= " *  '$name' => 'value'  // $type\n";

      $constructorSource .= "  call_user_func(array(\$this, 'set" . ucfirst($this->camelize($name)) . "'), \$properties['$name']);".PHP_EOL;
    }

    $propertiesComment .= " * );\n *\n * </code>\n *";

    $constructorComment->addParam(PhpDocElementFactory::getParam('array[mixed]', 'properties', str_replace("\n", PHP_EOL, $propertiesComment)));

    $constructorParameters = substr($constructorParameters, 2); // Remove first comma

    $constructorFunction = new PhpFunction('public', '__construct', $constructorParameters, $constructorSource, $constructorComment);
    $class->addFunction($constructorFunction);
    return $class;
  }

  /**
   * Implements the loading of the class object using setters and getters
   * @throws Exception if the class is already generated(not null)
   */
  protected function generateClass()
  {
    if ($this->class != null)
    {
      throw new Exception("The class has already been generated");
    }

    $config = Generator::getInstance()->getConfig();

    $class = new PhpClass($this->phpIdentifier, $config->getClassExists());

    $isResponseClass = (strpos($this->phpIdentifier, 'Response') !== false || strpos($this->phpIdentifier, 'Result') !== false) ? true : false;

    // Only add the constructor if type constructor is selected and not a response class
    if ($config->getNoTypeConstructor() == false && $isResponseClass == false)
    {
      $class = (count($this->members) > 5) ? $this->generateArrayParameterConstructor($class) : $this->generateParameterConstructor($class);
    }

    // Add member variables
    foreach ($this->members as $member)
    {
      $type = '';

      try
      {
        $type = Validator::validateType($member->getType());
        //if($type == 'CTSDeviceTypeEnum') var_export($member);
      }
      catch (ValidationException $e)
      {
        $type .= 'Custom';
      }

      $varName = Validator::validateNamingConvention($member->getName());
      $name = str_replace('__', '', $varName);

      // if a variable is all uppercase make only the first character upper for the method name
      $methodName = (strtoupper($name) == $name) ? ucfirst(strtolower($name)) : $name;

      // take variables and camelize them if needed
      $methodName = (strpos($methodName, '_') !== false) ? $this->camelize($methodName) : $methodName;

      $classComment = new PhpDocComment();
      $classComment->setVar(PhpDocElementFactory::getVar($type, $varName, ''));
      $classVar = new PhpVariable('private', $varName, '', $classComment);
      $class->addVariable($classVar);

      if ($config->getCreateAccessors())
      {
        // dont add setters for response and result classes
        if (!$isResponseClass)
        {
          $setterParameters = ((!in_array($type, $this->primitives)) ? "$type " : '') . "$$name";
          $setterSource = "  \$this->$varName = $$name;".PHP_EOL;
          $setterComment = new PhpDocComment();
          $setterComment->addParam(PhpDocElementFactory::getParam($type, $name, ''));
          $setterFunction = new PhpFunction('public', 'set' . ucfirst($methodName), $setterParameters, $setterSource, $setterComment);
          $class->addFunction($setterFunction);
        }

        $getterSource = "  return \$this->$name;".PHP_EOL;
        $getterComment = new PhpDocComment();
        $getterComment->setReturn(PhpDocElementFactory::getReturn($type, ''));
        $getterFunction = new PhpFunction('public', 'get' . ucfirst($methodName), '', $getterSource, $getterComment);
        $class->addFunction($getterFunction);
      }
    }

    $this->class = $class;
  }

  /**
   * Adds the member. Owerwrites members with same name
   *
   * @param string $type
   * @param string $name
   */
  public function addMember($type, $name)
  {
    $this->members[$name] = new Variable($type, $name);
  }

  /**
   *
   * @return int
   */
  public function getMemberCount()
  {
    return count($this->members);
  }

  private function camelize($lower_case_and_underscored_word)
  {
    $tmp = $lower_case_and_underscored_word;

    $tmp = preg_replace(array_keys(array('#/(.?)#e'    => "'::'.strtoupper('\\1')",
      '/(^|_|-)+(.)/e' => "strtoupper('\\2')")), array_values(array('#/(.?)#e'    => "'::'.strtoupper('\\1')",
      '/(^|_|-)+(.)/e' => "strtoupper('\\2')")), $tmp);

    return $tmp;
  }
}

