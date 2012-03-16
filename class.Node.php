<?php

/**
 * Node Class
 * 
 * Wraps Drupal's classless content system in an OO system.
 * This is a natural fit across Drupal's content architecture.
 * All pieces of content must have a content type, so Node is abstract.
 * Any content type must extend this class. To make construction easy,
 * annotation is supported through the external library Addendum.
 * http://code.google.com/p/addendum/
 *
 * Child properties that are correctly annotated will be automatically loaded
 * at construction.
 *
 * Any extending class must annotate its Drupal machine name with the annotation @MachineName().
 */
abstract class Node {

	###########################
	## Safe Access Constants ##
	###########################
	const CARDINALITY_ARRAY = 'array'; // Multi-valued array
	const CARDINALITY_SCALAR = 'scalar'; // Single-valued scalar
	const PRIMITIVE = 'primitive'; // int, boolean, string, etc. A non-object
	const ATTACHMENT = 'attachment'; // A drupal structured-array representing an attachment object
	
	####################
	## Public Members ##
	####################
	public $nid, $title, $created, $changed, $body, $uri, $machine_name, $raw_node;
	
	
	/**
	 * Creates a node object from a drupal node object (just a stdClass object), 
	 * typicially retrieved with the function node_load(). Since this is 
	 * an abstract class, Node is not directly instantiable.
	 *
	 * The node parameter is optional, so that ex-nihilo construction
	 * is supported.
	 * 
	 * @access public
	 * @param mixed $node (default: NULL)
	 * @return void
	 */
	public function __construct($node = NULL) {
		
		if ($node){
		
			##
			## Set simple values
			$this->nid = $node->nid;
			$this->title = $node->title;
			$this->created = $node->created;
			$this->changed = $node->changed;
			$this->uri = '/'.drupal_get_path_alias("node/{$node->nid}");
			$this->raw_node = $node;
			
			##
			## Set body value
			if (array_key_exists('und', $node->body) && array_key_exists('safe_value', $node->body['und'][0])) {
				$this->body = $node->body['und'][0]['safe_value'];
			}
			
			##
			## Through reflection and annotation, automatically instantiate annotated child properties
			foreach (array_keys(get_object_vars($this)) as $property) {
				$reflectedProperty = new ReflectionAnnotatedProperty(get_class($this), $property);
				
				if ($reflectedProperty->hasAnnotation('NodeProperties')) {
					$node_properties = $reflectedProperty->getAnnotation('NodeProperties');
					
					$this->{$property} = self::get(
						$node->{$node_properties->name}, 
						$node_properties->property_type, 
						$node_properties->cardinality
					);
				}
			}
			
		}
		
		##
		## Set machine name, regardless of whether a node was passed or not
		$this->machine_name = self::get_machine_name();
	}
	
	
	/**
	 * Generates a summary up to ${char_count} characters in length.
	 * 
	 * @access public
	 * @param int $char_count
	 * @return string
	 */
	public function get_summary($char_count) {
		
		if ($this->body) {
			return drupal_html_to_text(text_summary($this->body, NULL, $char_count), array('p'));
		}
		
	}
	
	/**
	 * Saves the current node object. Creates a new Drupal node in the database if 
	 * one does not already exist.
	 * 
	 * @access public
	 * @return void
	 */
	public function save() {
	
		##
		## Create or retrieve raw node object to edit
		$editNode = $this->nid && $this->raw_node ? $this->raw_node : new stdClass();
		
		if (!isset($editNode->type) || $editNode->type != $this->machine_name) {
			$editNode->type = $this->machine_name;
		}
		
		##
		## Set all annotated properties
		foreach (array_keys(get_object_vars($this)) as $property) {
			$reflectedProperty = new ReflectionAnnotatedProperty(get_class($this), $property);
			
			if ($reflectedProperty->hasAnnotation('NodeProperties')) {
				$nodeProperties = $reflectedProperty->getAnnotation('NodeProperties');
				
				self::set(
					$editNode->{$nodeProperties->name},
					$nodeProperties->property_type,
					$nodeProperties->cardinality, 
					$this->{$property}
				);
			}
		}
		
		##
		## Set node defaults
		$editNode->title = $this->title;
		$editNode->language = isset($editNode->language) && strlen($editNode->language) ? $editNode->language : 'und';
		
		##
		## Set node body
		$editNode->body['und'][0]['value'] = $this->body;
		$editNode->body['und'][0]['summary'] = text_summary($this->body, NULL, 100);
		$editNode->body['und'][0]['format'] = 'full_html';
		
		##
		## Save to DB
		node_object_prepare($editNode);
		node_save($editNode);
		
		##
		## Set nid and raw node, in the event that this is a newly created node
		$this->nid = $editNode->nid;
		$this->raw_node = node_load($this->nid);
		$this->uri = '/'.drupal_get_path_alias("node/{$this->nid}");
		
	}
	
	
	/**
	 * If a child class is properly annotated with its machine name,
	 * returns that. Throws an exception if not able to retrieve
	 * a valid annotation.
	 * 
	 * @access public
	 * @static
	 * @return string
	 * @throws MissingNodeAnnotationException if the @MachineName annotation is improperly defined.
	 */
	public static function get_machine_name() {
		
		##
		## Get annotated machine name of class
		$reflectedClass = new ReflectionAnnotatedClass(get_called_class());
		
		if ($reflectedClass->hasAnnotation('MachineName') && strlen($reflectedClass->getAnnotation('MachineName')->value)) {
			return $reflectedClass->getAnnotation('MachineName')->value;
		} else {
			throw new MissingNodeAnnotationException('Missing @MachineName annotation in class: ' . get_called_class());
		}
	}
	
	/**
	 * Returns all published nodes that match the type of the given caller, specified
	 * in the @MachineName annotation of the extending class. This can also be called
	 * abstractly with Node::get_all_published(), but will probably be pretty slow.
	 * 
	 * @access public
	 * @static
	 * @return array
	 */
	public static function get_all_published(){
	
		##
		## Get all published results from DB
		if (get_called_class() === 'Node') {
			$results = db_query("SELECT nid FROM {node} WHERE status = 1 ORDER BY created DESC")->fetchAll();
		} else {
			$results = db_query(
				"SELECT nid FROM {node} WHERE type = :type AND status = 1 ORDER BY created DESC",
				array(':type' => self::get_machine_name())
			)->fetchAll();
		}

		##
		## Instantiate with factory initalizer
		return array_map( function($result) { return NodeClassRegistry::init_class($result->nid); }, $results );
		
	}
	
	
	/**
	 * Returns a single node of the type specified by the callers type,
	 * with a node id of parameter ${nid}. The type of the node with node
	 * id ${nid} must be equivalent to the type of the callers @MachineName,
	 * or NULL will be returned. For instance, if node 17 is a node
	 * of type 'cashew', calling PirateShip::get_by_nid(17) will
	 * return NULL. Can also be called abstractly with Node::get_by_nid().
	 * 
	 * @access public
	 * @static
	 * @param mixed $nid
	 * @return mixed
	 */
	public static function get_by_nid($nid) {
	
		if (get_called_class() === 'Node' ) {
			return NodeClassRegistry::init_class($nid);
		}
		elseif ($result = NodeClassRegistry::init_class($nid)) {
			##
			## Verify that loaded class type is equivalent to the class type with which this method was called
			if($result->machine_name == self::get_machine_name()) {
				return $result;
			} else {
				return NULL;
			}
		} else {
			return NULL;
		}
		
	}
	
	/**
	 * Safely descends the nested structure of a standard Drupal $node array and retrieves the given property.
	 * If a value has not been set, returns a default as specified in the value_defaults function.
	 * 
	 * @access public
	 * @static
	 * @param mixed $property The sub-array of the node to descend. For instance, $node->person_first_name
	 * @param mixed $property_type Whether the property is a primitive value (e.g. int, text) or an attachment object
	 * @param mixed $cardinality Whether 1 value is expected, or many
	 * @return mixed
	 */
	private static function get($property, $property_type, $cardinality) {
		
		if (array_key_exists('und', $property)) {
		
			if ($cardinality == self::CARDINALITY_ARRAY) {
			
				$return_values = array();
				foreach($property['und'] as $values) {
					$return_values[] = $property_type == self::PRIMITIVE 
						? (array_key_exists('safe_value', $values) ? ($values['safe_value']) : ($values['value'])) 
							: (new Attachment($values));
				}
				
				return $return_values;

			} elseif ($cardinality == self::CARDINALITY_SCALAR) {
			
				return $property_type == self::PRIMITIVE 
					? (array_key_exists('safe_value', $property['und'][0]) ? $property['und'][0]['safe_value'] : $property['und'][0]['value']) 
						: (new Attachment($property['und'][0]));
				
			}

		} else {
			return self::value_defaults($cardinality, $property_type);
		}
	}
	
	
	/**
	 * Safely descends the nested structure of a standard Drupal $node array and sets the given property
	 * to the provided value.
	 * 
	 * @access private
	 * @static
	 * @param mixed &$property
	 * @param mixed $cardinality
	 * @param mixed $value
	 * @return void
	 */
	private static function set(&$property, $property_type, $cardinality, $value) {
		
		##
		## Only set the node key if we have a non-default value
		if ($value != self::value_defaults($cardinality, $property_type)) {
			
			if ($cardinality == self::CARDINALITY_ARRAY) {
				
				$property['und'] = array();
				foreach($value as $v) {
					$property['und'][] = array(
						'value' => $v,
						'format' => NULL,
						'safe_value' => $v
					);
				}
				
			} elseif ($cardinality == self::CARDINALITY_SCALAR) {
				
				$property['und'] = array();
				$property['und'][] = array(
					'value' => $value,
					'format' => NULL,
					'safe_value' => $value
				);
				
			}
			
		} else {
			// A value-less node key points to an empty array
			$property = array();
		}
			
	}
	
	
	/**
	 * Returns sensible default values for a given cardinality and property type.
	 * 
	 * @access private
	 * @static
	 * @param mixed $cardinality
	 * @param mixed $property_type
	 * @return mixed
	 */
	private static function value_defaults($cardinality, $property_type) {
	
		if ($cardinality == self::CARDINALITY_ARRAY) {
		
			if ($property_type == self::PRIMITIVE) { return array(); }
			elseif ($property_type == self::ATTACHMENT) { return array(); }
			
		} elseif ($cardinality == self::CARDINALITY_SCALAR) {
		
			if ($property_type == self::PRIMITIVE) { return ''; }
			elseif ($property_type == self::ATTACHMENT) { return NULL; }
			
		}
		
	}

}

?>