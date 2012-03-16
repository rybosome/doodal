<?php

class Attachment {

	public $filename, $internal_uri, $external_uri, $mime, $size;

	public function __construct($node_data) {
		
		$this->filename = $node_data['filename'];
		$this->internal_uri = $node_data['uri'];
		$this->external_uri = file_create_url($node_data['uri']);
		$this->mime = $node_data['filemime'];
		$this->size = $node_data['filesize'];
		
	}

}

?>