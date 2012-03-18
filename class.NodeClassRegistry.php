<?php


/**
 * Factory for child classes of Node.
 * Although the type of node is not needed
 * to be provided to the factory, this is
 * not an abstract factory - the object
 * returned will be an instance of the child
 * class, not an interface over the abstract
 * node class.
 *
 * To be visible, a class extending node
 * must implement the 'MachineName' annotation. 'MachineName'
 * requires providing the Drupal "machine name" as
 * a parameter.
 */
class NodeClassRegistry {

	/**
	 * Id that any registry data will be cached under
	 */
	const DOODAL_CACHE_ID = 'doodal:node_registry';
	
	
	/**
	 * Attempts to construct a node-extended object from a node id.
	 * Will look through all known classes with the annotation tag 'MachineName'.
	 *
	 * If not able to construct an object, returns NULL. 
	 *
	 * @access public
	 * @static
	 * @param mixed $nid Node ID of the node to construct.
	 * @return mixed
	 */
	public static function init_class($nid) {
		
		$node = $nid && strlen($nid) ?  node_load($nid) : NULL;
		
		if($node) {

			##
			## Initialize/get node registry
			$registry = self::init_registry();
			
			##
			## Return instantiated class if properly annotated, else NULL
			if (array_key_exists($node->type, $registry)){
				return new $registry[$node->type]($node);
			} else {
				return NULL;
			}


		} else {
			return NULL;
		}
		
	}
	
	
	/**
	 * Returns an associative array of classes known to extend Node.
	 * Pulls this array from cache if available - if not, generates
	 * and caches it.
	 *
	 * Class names are keyed on their machine name.
	 * 
	 * @access private
	 * @static
	 * @return array
	 */
	private static function init_registry() {
	
		$cache = cache_get(self::DOODAL_CACHE_ID);
	
		##
		## Attempt to get registry from cache
		if ($cache  && !empty($cache->data)) {
			return $cache->data;
		}
		
		##
		## Generate node registry, cache, and return
		else {
			##
			## Load all classes registered for autoloading not in an ignored (i.e. system) module	
			$results = db_query(
				"SELECT name FROM {registry} WHERE type = 'class' AND module != '' AND module NOT IN (:modules) ORDER BY name",
				array(':modules' => self::get_ignored_modules())
			)->fetchAll();

			##
			## Get subset of classes marked as "node"			
			$registry = array();
			foreach ($results as $result) {
				$reflectedClass = new ReflectionAnnotatedClass($result->name);
				if ($reflectedClass->hasAnnotation('MachineName')) {
					$registry[$reflectedClass->getAnnotation('MachineName')->value] = $result->name;
				}
			}
			
			##
			## Cache results and return
			cache_set(self::DOODAL_CACHE_ID, $registry, 'cache', CACHE_PERMANENT);
			return $registry;
		}

	}
	
	
	/**
	 * Returns an array of modules which should not be checked for classes extending Node.
	 * This is mostly system modules.
	 * 
	 * @access private
	 * @static
	 * @return array
	 */
	private static function get_ignored_modules() {
		return array(
			'aggregator',
			'block',
			'blog',
			'book',
			'color',
			'comment',
			'contact',
			'contextual',
			'dashboard',
			'dblog',
			'field',
			'field_sql_storage',
			'field_ui',
			'file',
			'filter',
			'forum',
			'help',
			'image',
			'list',
			'locale',
			'menu',
			'node',
			'number',
			'openid',
			'options',
			'overlay',
			'path',
			'php',
			'poll',
			'profile',
			'rdf',
			'search',
			'shortcut',
			'simpletest',
			'statistics',
			'standard',
			'syslog',
			'system',
			'taxonomy',
			'testing',
			'text',
			'toolbar',
			'tracker',
			'translation',
			'trigger',
			'update',
			'user',
			'doodal'
		);
	}
	
}

?>