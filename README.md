# Doodal

Doodal is an object-oriented wrapper library around Drupal 7's node system.
It allows you to interact with Drupal nodes in a more object-oriented way.

## Is Doodal right for me?

Doodal is _not_ right for you if...

- You aren't a developer
- You're happy with Drupal's existing nodes
- Your website is a basic, content-driven website
- You are not running PHP 5.3.x or greater
- You are not running Drupal 7

Doodal may be right for you if...

- You are writing a web app in Drupal that truly is a web app, not just a content-driven website (i.e. you've used the term 'business logic')
- You're in a situation where your website's data is intertwined with its content
- You don't like spaghetti
- You are running PHP 5.3.x or greater
- You are running Drupal 7

## What's the point?

Object-oriented programming is not the only style of programming, the best,
nor is it always the most appropriate. It is, however, an appropriate
abstraction over Drupal's content system.

Drupal nodes imitate object orientation, but they lack true encapsulation and inheritance.
In a web application where your content, which is represented by nodes, is also data that needs
to be handled and processed in special ways (such as by interacting with a web service),
the procedural logic required to compensate for this becomes spaghetti-like and difficult to maintain. 
Although it's not really possible to do true MVC in Drupal, Doodal will give you a closer approximation to models 
than what you get out of the box.

Doodal doesn't enable you to do anything that's not possible with vanilla Drupal, but it may make
your code easier to write and maintain.

## Let's see it

In the context of these next few examples, assume that we have a content type
called 'Person', containing all of the fields you would logically expect. There
is a person node with node id 15, in particular, that we will look at.

#### Gettin'

Although there are many ways of doing it, one way that you may have of getting
all published Person nodes may be the following:

```php
<?php
// Regular ol' Drupal
$results = db_query("SELECT nid FROM {node} WHERE type = 'person' AND status = 1")->fetchAll();
$nids = array_map( function($x) {return $x->nid;}, $results );
$people = node_load_multiple($nids);
?>
```

Hmm. What if we could do the following?

```php
<?php
// Doodal
$people = Person::get_all_published();
?>
```

Drupal makes it dead simple to get a single node.

```php
<?php
// Drupal
$person = node_load(15);
?>
```

...so, Doodal does too:

```php
<?php
// Doodal
$person = Person::get_by_nid(15);
?>
```

...but, we also benefit from some typing(ish), here. What if we try to load the person with node id
27, and that's actually a content type called `Animal`?

```php
<?php
$person = Person::get_by_nid(27);
echo $person ? 'Yay' : 'Nah';
// 'Nah'
// $person is NULL
?>
```

#### Ok, what do I have to do?

So far, not much. You'll just need a Person class in your application. The code
will look a little something like this:

```php
<?php
/** @MachineName('person') */
class Person extends Node {}
?>
```

Thanks to the magic of the [Addendum](http://code.google.com/p/addendum/) library, reflection, and [late static binding](http://php.net/manual/en/language.oop5.late-static-bindings.php) (this is why a minimum of PHP 5.3 is required),
we get the above functionality by writing two lines of code.

The code for a full implementation is a little bit more involved (you have to annotate each property you want 
loaded from a node beyond those common to all nodes), but this will give you a fully functional class for your simple use cases.

#### Fine. What else does it do?

Are you tired of writing code that looks like the following?

```php
<!-- I just want to echo out this person node's first name without getting PHP warnings -->
<?= array_key_exists('und', $node->first_name) ? $node->first_name['und'][0]['safe_value'] : ''; // Gross =( ?>
```

I certainly am, that's obnoxious. It'd be so much nicer to just write this:

```php
<!-- Doodal -->
<?= $node->first_name  // =) ?>
```

If you've ever had to manually create a node, then you've probably written something like the following:

```php
<?php
// Drupal
$new_node = new stdClass();
$new_node->title = 'Pirate Ship';
$new_node->type = 'things_i_want_to_be_when_i_grow_up';
$new_node->how_awesome['und'] = array();
$new_node->how_awesome['und'][] = array('safe_value' => 'You have no idea');
node_object_prepare($new_node);
node_save($new_node);
?>
```

It doesn't look too bad, I guess...but that setting of the `how_awesome` property is yucky and unintuitive. Can we do better?

```php
<?php
// Doodal
$new_node = new ThingsIWantToBeWhenIGrowUp();
$new_node->title = 'Pirate Ship';
$new_node->how_awesome = 'You have no idea';
$new_node->save();
?>
```

Much better. We only shaved off 3 lines of code, but this is significantly more readable.

#### Are there any other minor conveniences?

Of course; I'm glad you asked such a specific question!

```php
<?php
echo $node->url;
// '/people/jack-black', or whatever your aliased URL is. If you haven't aliased it, that's /node/${nid}

echo $node->get_summary(100);
// The first 100 characters of the body text truncated logically

print_r($node->raw_node);
// Gets the actual Drupal node stdClass object used for construction
?>
```

More importantly, this module will drop a variable named `$oNode` into the scope of all of your node template files.
So, the following code in your `node--person.tpl.php` file would work just fine:

```php
<h2><?= $oNode->get_summary(200) ?></h2>
```
#### Um...what if I want to instantiate a Node object, but I don't know offhand which class is going to implement it?

Internally, Doodal has to instantiate some class extending Node without knowing exactly what that is.
Enter the ol' gang of four pattern, the (not-quite) abstract-factory!

```php
<?php
$some_node = Node::get_by_nid(15);
echo get_class($some_node);
// 'Person'
?>
```

What happens if we really don't have a class to implement the node in question?

```php
<?php
// We don't have an animal class, but 27 is a node id for an animal node
$node = Node::get_by_nid(27);

$animal_name = $node ? 'Bandit' : 'lksjdf';
// lksjdf
// We will never be able to pronounce our animal's name
?>
```

Caching is employed for speed reasons, so always clear the cache after writing a new implementing class.

## Give me an actual, legitimate, front-to-back, well is you feeling that, put one hand up, can you repeat that, example.

Here's your (fictional but reasonable) situation: your application has a content type called `Event`. This has the fields you would
probably expect, so I won't list those, beyond noting that every event must have an event coordinator - this
is a person, which is a content type that we already have in our pretend system, right? Your application allows
the admins to edit some of the basic content of an event, but the important data (namely, when the event is)
is going to come from a web service. Since we aren't scruffy devs, here, we're going to display these
events all purty-like on the front-end.

Our application will end up containing the following:

`class.Person.php`

```php
<?php
/** @MachineName('person') */
class Person extends Node {}
?>
```

`class.Event.php`

```php
<?php
/** @MachineName('event') */
class Event extends Node {
	
	// These are the values that content admins can edit through the standard node interface
	public
		/** @NodeProperties(name = 'address_1', property_type = Node::PRIMITIVE, cardinality = Node::CARDINALITY_SCALAR) */		
		$address1, 

		/** @NodeProperties(name = 'address_2', property_type = Node::PRIMITIVE, cardinality = Node::CARDINALITY_SCALAR) */		
		$address2, 

		/** @NodeProperties(name = 'city', property_type = Node::PRIMITIVE, cardinality = Node::CARDINALITY_SCALAR) */		
		$city, 

		/** @NodeProperties(name = 'state', property_type = Node::PRIMITIVE, cardinality = Node::CARDINALITY_SCALAR) */		
		$state, 

		/** @NodeProperties(name = 'zip', property_type = Node::PRIMITIVE, cardinality = Node::CARDINALITY_SCALAR) */		
		$zip,
		
		/** @NodeProperties(name = 'coordinator_nid', property_type = Node::PRIMITIVE, cardinality = Node::CARDINALITY_SCALAR) */
		$coordinator_nid;
		
	// These values need to come from a web service
	public $start_time, $end_time;
	
	// We will cache the actual coordinator object here
	private $coordinator;
		
	public function __construct($node) {
		
		##
		## We need to remember to call the parent constructor
		parent::__construct($node);
		
		##
		## Get stuffz from webservice!
		if ($data = $this->webservice_get) {
			$start_time = $data->start_time;
			$end_time = $data->end_time;
		}
	}
	
	public function get_coordinator() {
		if (! $this->coordinator) {
			$this->coordinator = new Person(
				node_load($this->coordinator_nid)
			);
		}
		return $this->coordinator;
	}
	
	private function webservice_get( {
		$ch = curl_init("http://api.com/event/?id={$this->nid}");
		
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		$results = curl_exec($ch);
		curl_close($ch);
		
		return $results ? json_decode($results) : NULL;
	}
}
?>
```

`node--event.tpl.php`

```php
<h1><?= $oNode->title ?></h1>
<h2><?= $oNode->get_summary(100) ?></h2>

<p class="event-timing">
	<?= date('M/d/Y', $oNode->start_time) ?> - <?= date('M/d/Y', $oNode->end_time) ?>
</p>

<?= $oNode->body ?>

Event Coordinator:
<a href="<?= $oNode->get_coordinator()->uri ?>">
	<?= $oNode->get_coordinator()->title ?>
</a>
```

The true benefit to using Doodal isn't obvious until you compare the code you would write with it to the code you would
write without it. The God's honest is that I don't want to write that code (this is why I wrote Doodal instead),
so you'll have to imagine it. 

If trying to imagine that nest of spaghetti-code, pseudo-namespaced function calls, cruft, bloat, `array_key_exists` 
and what-have-you makes your head hurt, consider this a salve - it'll significantly reduce the pain of 
managing a web application where your data and your content overlap, since you no longer have to play the weird, 
dancey-mergey game whenever you're trying to get parity between the two.


## Anything else I should know?

You should probably know the annotation schema for properties. There are three things you're concerned with.

- Name
- Type
- Cardinality

Name: This is simple. Just the name of the field in the raw Drupal node.

Type: This is either going to be `Node::PRIMITIVE` or `Node::ATTACHMENT`. If it's not a file (image or otherwise), it's a primitive.

Cardinality: Your options are `Node::CARDINALITY_ARRAY` and `Node::CARDINALITY_SCALAR`. 1 value = scalar, many = array.

Note also that any class extending Node must have the `@MachineName` annotation, containing the Drupal type in question. Attempting to
instantiate a class extending Node without this annotation will throw an exception.

## What's with the name?

The name came from my step-brother Nathan, who referred to Drupal as 'doodle' once. I figured that the
double 'oo' of 'Doodal' lined up nicely with the term "Object-Oriented". The intentional misspelling of 'doodle'
also makes that nice '-al' ending, keeping it in line with 'Drupal'.

## Why isn't this on Drupal.org?

Well, frankly this module is pretty non-Drupal. Most Drupal-ers maintain a firm stance against the need for
object orientation. I'm of the opinion that Drupal, being a CMS/platform, is not well suited to the same
tasks that a web-framework (Rails, Grails, ASP.NET MVC, CakePHP, Django, Wicket, etc. etc. etc.) is suitable for.
Doodal is useful when you're forced to use Drupal like one of the aforementioned web frameworks, which is something
that I doubt the community at Drupal.org would get excited about (or agree with).

## What's missing?

- Doodal doesn't account for languages other than `default or none`.
- Body summaries cannot be edited programmatically
