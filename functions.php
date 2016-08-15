function my_content_filter($html) {
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));

    //Evaluate Anchor tag in HTML
    $xpath = new DOMXPath($dom);
    $hrefs = $xpath->evaluate("/html/body//a");

    if ($hrefs->length > 0) {
        for ($i = 0; $i < $hrefs->length; $i++) {
            $href = $hrefs->item($i);
            if ($href->getAttribute("target") == "_blank") {
                $old_rel = $href->getAttribute("rel");
                $new_rel = "noreferrer";
                if (!empty($old_rel)) {
                    $new_rel = $new_rel . " " . $old_rel;
                }
                $href->setAttribute("rel", $new_rel);
            }
        }
        $html = $dom->saveHTML();
    }

    return $html;
}

add_filter("the_content", "my_content_filter");
