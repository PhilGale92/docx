<?php
/**
 * @name htmlEntitiesEncode
 * @desc A wrapper for PHP's inbuilt htmlentities function
 * @param string $string
 * @param bitmask $bitMask (Optional, Defaults to ENT_QUOTES)
 * @param boolean $doubleEncode (Optional, Defaults to TRUE)
 * @param string $encoding (Optional, allows you to over-ride the Config encoding)
 * @return $string
 */
function htmlEntitiesEncode($string, $bitMask = ENT_QUOTES, $doubleEncode = true, $encoding = null){
	if ($encoding == null)
		$encoding = 'UTF-8';
	return htmlentities($string, $bitMask, $encoding, $doubleEncode);
}
	
	
/**
 * @name getArray
 * @desc Converts a domElement into an array
 * @param domelement $node
 * @return array OR false
 */
function getArray($node){
	$array = false;
	if ($node->hasAttributes()){
		foreach ($node->attributes as $attr){
			$array[$attr->nodeName] = $attr->nodeValue;
		}
	}

	if ($node->hasChildNodes()){
		if ($node->childNodes->length == 1)	{
			$array[$node->firstChild->nodeName] = $node->firstChild->nodeValue;
		} else {
			foreach ($node->childNodes as $childNode){
				if ($childNode->nodeType != XML_TEXT_NODE)	{
					$array[$childNode->nodeName][] = getArray($childNode);
				}
			}
		}
	}

	return $array;
}

/**
 * @name arrayComplexSearch
 * @desc Provides a utility method for searching for a key + value pair within a multidimensional array
 * @param array $array
 * @param string $key
 * @param string $value
 * @return Ambigous <multitype:unknown , multitype:>
 */
function arrayComplexSearch($array, $key, $value){
	$results = array();

	if (is_array($array)){
		if (isset($array[$key]) && $array[$key] == $value)
			$results[] = $array;

		foreach ($array as $subarray)
			$results = array_merge($results, arrayComplexSearch($subarray, $key, $value));
	}
	return $results;
}