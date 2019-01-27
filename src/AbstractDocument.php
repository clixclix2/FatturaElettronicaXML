<?php

/**
 * Quetso software � derivato dal programma php-e-invoice-it <https://github.com/taocomp/php-e-invoice-it>
 * @license GPL v3.0 <http://www.gnu.org/licenses/gpl.html>
 * @copyright 2018 Taocomp s.r.l.s. <https://taocomp.com>
 *
 * @since 2019-01-27 Creazione della libreria FatturaElettronicaXML <https://github.com/clixclix2/FatturaElettronicaXML>
 * @author Itala Tecnologia Informatica S.r.l. <https://www.itala.net>
 *
 */


abstract class AbstractDocument
{
	/**
	 * DOMDocument object
	 */
	protected $dom = null;
	
	/**
	 * Filename
	 */
	protected $filename = null;
	
	/**
	 * Optional prefix path where to save this document
	 */
	protected $prefixPath = null;
	
	/**
	 * Constructor
	 */
	public function __construct( $file = null )
	{
		$this->dom = new \DOMDocument('1.0', 'UTF-8');
		$this->dom->formatOutput = true;
		
		if (null === $file) {
			$root = $this->dom->createElementNS(
				static::ROOT_NAMESPACE,
				static::ROOT_TAG_PREFIX . ':' . static::ROOT_TAG_NAME);
			$root->setAttributeNS(
				'http://www.w3.org/2000/xmlns/',
				'xmlns:ds',
				'http://www.w3.org/2000/09/xmldsig#');
			$root->setAttributeNS(
				'http://www.w3.org/2000/xmlns/',
				'xmlns:xsi',
				'http://www.w3.org/2001/XMLSchema-instance');
			$root->setAttributeNS(
				'http://www.w3.org/2001/XMLSchema-instance',
				'schemaLocation',
				static::SCHEMA_LOCATION);
			
			if (false === is_a($root, '\DOMElement')) {
				throw new \Exception('Method createRootElement must return a \DOMElement');
			}
			
			if (false === is_array(static::$templateArray)) {
				throw new \Exception('static templateArray must be an array');
			}
			
			$this->addElementsFromArray($root, static::$templateArray);
			$this->dom->appendChild($root);
		} else if (is_readable($file)) {
			$this->load($file);
		} else {
			throw new \Exception("File '$file' not found or not readable");
		}
	}
	
	/**
	 * Returns document class name
	 *
	 * @return string
	 */
	protected function getClassName()
	{
		$classArray = explode('\\', get_class($this));
		return array_pop($classArray);
	}
	
	/**
	 * DOMDOCUMENT, DOMXPATH, XML
	 ***************************************************************************
	 */
	
	/**
	 * Returns the document as XML
	 */
	public function asXML( $normalize = true )
	{
		if (true === $normalize) {
			$this->normalize();
		}
		
		return $this->dom->saveXML(null, LIBXML_NOEMPTYTAG);
	}
	
	/**
	 * Returns the DOMDocument object
	 *
	 * @return \DOMDocument
	 */
	public function getDOM()
	{
		return $this->dom;
	}
	
	/**
	 * Removes empty elements
	 */
	public function normalize()
	{
		// TODO: recursive
		for ($i = 0; $i < 4; $i++) {
			foreach( $this->query('//*[not(node())]') as $node ) {
				$node->parentNode->removeChild($node);
			}
		}
		
		return $this;
	}
	
	/**
	 * Query for elements.
	 *
	 * - Absolute paths: omit root element (for example p:FatturaElettronica)
	 * - Tags are prefixed with "(.)//"
	 * - Relative paths are prefixed with "(.)//"
	 *
	 * $context can be a string or a \DOMNode
	 *
	 * @return \DOMNodeList
	 */
	public function query( $expr, $context = null, $registerNodeNS = true )
	{
		$strpos = strpos($expr, '/');
		
		if (false === $strpos) {
			$expr = "//$expr";
			
			if (null !== $context) {
				$expr = ".$expr";
			}
		} else if ($strpos === 0) {
			// Absolute path cannot have a context
			if (null !== $context) {
				throw new \Exception("Cannot specify a context with an absolute path ($expr)");
			}
			
			if ($expr === '/') {
				$expr = '';
			}
			
			$expr = '/' . static::ROOT_TAG_PREFIX . ':' . static::ROOT_TAG_NAME . $expr;
		} else if ($strpos > 1) {
			$expr = "//$expr";
			
			if (null !== $context) {
				$expr = ".$expr";
			}
		}
		
		if (null !== $context) {
			$context = $this->getElement($context);
		}
		
		$xpath = new \DOMXpath($this->dom);
		return $xpath->query($expr, $context, $registerNodeNS);
	}
	
	/**
	 * FILENAME, PATH, LOAD/SAVE FILE
	 ***************************************************************************
	 */
	
	/**
	 * Get destination dir
	 */
	public static function getDefaultPrefixPath()
	{
		return static::$defaultPrefixPath;
	}
	
	/**
	 * Get filename
	 */
	public function getFilename()
	{
		return $this->filename;
	}
	
	/**
	 * Set filename
	 */
	public function setFilename( $filename )
	{
		$this->filename = $filename;
		return $this;
	}
	
	/**
	 * Set destination dir (common prefix path when saving invoices)
	 */
	public static function setDefaultPrefixPath( $dir )
	{
		$dir = realpath($dir);
		
		if (false === $dir) {
			throw new \Exception("Cannot access to '$dir'");
		}
		if (false === is_writeable($dir)) {
			throw new \Exception("Directory '$dir' is not writeable");
		}
		
		static::$defaultPrefixPath = $dir;
	}
	
	/**
	 * Get prefix path for current object
	 */
	public function getPrefixPath()
	{
		return $this->prefixPath;
	}
	
	/**
	 * Load a document from file
	 */
	protected function load( $filename, $options = 0 )
	{
		if (false === $this->dom->load($filename, $options)) {
			throw new \Exception("Cannot load file '$filename'");
		}
		
		return $this;
	}
	
	/**
	 * Load a document from string
	 * C.C.
	 */
	protected function loadXML( $xml, $options = 0 )
	{
		if (false === $this->dom->loadXML($xml, $options)) {
			throw new \Exception("Cannot load file XML");
		}
		
		return $this;
	}
	
	/**
	 * Save document to file.
	 * If set, prepend static::$defaultPrefixPath to path.
	 * Overwrite existing file if $overwrite is true.
	 */
	public function save( $overwrite = false, $normalize = true )
	{
		$prefixPath = '.';
		if (null !== $this->prefixPath) {
			$prefixPath = $this->prefixPath;
		} else if (null !== static::$defaultPrefixPath) {
			$prefixPath = static::$defaultPrefixPath;
		}
		$prefixPath = realpath($prefixPath);
		
		if (false === $prefixPath) {
			throw new \Exception("Cannot set a valid prefixPath ('$prefixPath')");
		}
		
		$filename = $this->getFilename();
		
		if (false === is_string($filename) || empty($filename)) {
			throw new \Exception("Filename '$filename' empty or invalid");
		}
		
		$dest = $prefixPath . DIRECTORY_SEPARATOR . $filename;
		
		$className = $this->getClassName();
		
		if (file_exists($dest) && false === $overwrite) {
			throw new \Exception("$className '$dest' already exists");
		}
		
		if (true === $normalize) {
			$this->normalize();
		}
		
		// if (false === $this->dom->save($dest, LIBXML_NOEMPTYTAG)) {
		if (false === $this->dom->save($dest)) {
			throw new \Exception("Cannot save $className to '$dest'");
		}
		
		return $this;
	}
	
	/**
	 * Set optional destination dir for current object
	 */
	public function setPrefixPath( $dir )
	{
		$dir = realpath($dir);
		
		if (false === $dir) {
			throw new \Exception("Cannot access to '$dir'");
		}
		if (false === is_writeable($dir)) {
			throw new \Exception("Directory '$dir' is not writeable");
		}
		
		$this->prefixPath = $dir;
		
		return $this;
	}
	
	/**
	 * ELEMENTS
	 ***************************************************************************
	 */
	
	/**
	 * Add an element
	 */
	public function addElement( $element, $parent, $beforeRef = null )
	{
		$parent = $this->getElement($parent);
		
		if (is_string($element)) {
			$element = $this->dom->createElement($element);
		} else if (false === $element instanceOf \DOMNode) {
			throw new \Exception('Invalid $element parameter');
		}
		
		if (null === $beforeRef) {
			$parent->appendChild($element);
		} else {
			$beforeRef = $this->getElement($beforeRef);
			$parent->insertBefore($element, $beforeRef);
		}
		
		return $this;
	}
	
	/**
	 * Recursively adds elements from array
	 */
	public function addElementsFromArray( $parent, array $array )
	{
		$parent = $this->getElement($parent);
		
		foreach ($array as $k => $v) {
			if (true === is_array($v)) {
				$node = $this->dom->createElement($k);
				$this->addElementsFromArray($node, $v);
			} else {
				$node = $this->dom->createElement($k, $v);
			}
			$parent->appendChild($node);
		}
	}
	
	/**
	 * Query document for specified element.
	 *
	 * Throws an exception if query returns 0 or N>1 elements.
	 */
	public function getElement( $expr, $context = null, $index = null )
	{
		if ($expr instanceOf \DOMNode) {
			return $expr;
		} else if (false === is_string($expr)) {
			throw new \Exception('Invalid param $expr');
		}
		
		if (null !== $context) {
			$context = $this->getElement($context);
		}
		
		$elements = $this->query($expr, $context);
		$count = $elements->length;
		
		if ($count !== 1) {
			$errSuffix = null === $context ? "" : " (context: {$context->nodeName})";
		}
		
		if ($count > 1 && $index === null) {
			$nodeValues = 'values:';
			foreach ($elements as $element) {
				$nodeValues .= " {$element->nodeName}:{$element->nodeValue},";
			}
			throw new \Exception("Element '$expr' returns $count elements ($nodeValues)$errSuffix");
		} else if ($count === 0) {
			throw new \Exception("Element not found ($expr)$errSuffix");
		}
		
		if ($index === null) {
			$index = 0;
		} elseif ($index === -1) {
			$index = $count - 1;
		}
		
		return $elements->item($index);
	}
	
	/**
	 * Remove an element
	 */
	public function removeElement( $element )
	{
		$element = $this->getElement($element);
		$parent = $element->parentNode;
		$parent->removeChild($element);
		
		return $this;
	}
	
	/**
	 * VALUES
	 ***************************************************************************
	 */
	
	/**
	 * Get value
	 */
	public function getValue( $expr, $context = null )
	{
		return $this->getElement($expr, $context)->nodeValue;
	}
	
	/**
	 * Set value for a given element, if the element is unique (by tag or xpath).
	 * Throws an exception otherwise.
	 */
	public function setValue( $expr, $value, $context = null )
	{
		$value = htmlspecialchars($value, ENT_XML1); // C.C.
		$this->getElement($expr, $context)->nodeValue = $value;
		
		return $this;
	}
	
	/**
	 * Set same value $value to all elements retrieved through $expr
	 */
	public function setValueToAll( $expr, $value, $context = null )
	{
		$value = htmlspecialchars($value, ENT_XML1); // C.C.
		
		$elements = $this->query($expr, $context);
		
		foreach ($elements as $element) {
			$element->nodeValue = $value;
		}
		
		return $this;
	}
	
	/**
	 * Set values from an associative array. Keys must return just one element.
	 * Array keys are relative to $context.
	 */
	public function setValues( $context, array $array )
	{
		foreach ($array as $k => $v) {
			$this->setValue($k, $v, $context);
		}
		
		return $this;
	}
	
	/**
	 * Recursively set values from array
	 */
	public function setValuesFromArray( $context, array $array )
	{
		$context = $this->getElement($context);
		
		foreach ($array as $k => $v) {
			if (true === is_array($v)) {
				$node = $this->getElement($k, $context);
				$this->setValuesFromArray($node, $v);
			} else {
				$this->setValue($k, $v, $context);
			}
		}
	}
	
	/**
	 * Set values from an associative array. Keys may return N elements.
	 * Array keys are relative to $context.
	 */
	public function setValuesToAll( $context, array $array )
	{
		foreach ($array as $k => $v) {
			$this->setValueToAll($k, $v, $context);
		}
		
		return $this;
	}
}
