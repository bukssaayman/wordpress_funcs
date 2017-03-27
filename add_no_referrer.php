<?php

function my_content_filter($html) {
   if (empty($html)) {
		return $html;
	}

	$dom = new DOMDocument();
	libxml_use_internal_errors(true);
	$dom->loadXML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8')); // http://stackoverflow.com/questions/4879946/how-to-savehtml-of-domdocument-without-html-wrapper#answer-4880227

	//Evaluate Anchor tag in HTML
	$xpath = new DOMXPath($dom);
	$hrefs = $xpath->evaluate("//a");

	if ($hrefs->length > 0) {
		for ($i = 0; $i < $hrefs->length; $i++) {
			$href = $hrefs->item($i);

			if ($href->getAttribute('target') == '_blank') {
				$old_rel = $href->getAttribute('rel');

				if (empty($old_rel)) {
					$new_rel = 'noreferrer';
				}
				else {
					if (strpos('noreferrer', $old_rel) === false) {
						$new_rel = 'noreferrer ' . $old_rel;
					}
				}

				$href->setAttribute('rel', $new_rel);
			}
		}

		$html = $dom->saveHTML();
	}

	return $html;
}

add_filter("the_content", "my_content_filter");
