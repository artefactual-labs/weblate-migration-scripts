<?php

function parseArgs($argv)
{
  $args = array();
  $flags = array();

  foreach($argv as $index => $value)
  {
    // Ignore $argv[0]
    if ($index)
    {
      if (substr($value, 0, 2) === "--")
      {
        // Allow options to be flags or have values
        if (substr_count($value, '='))
        {
          $valueStart = strpos($value, '=') + 1;
          $name = strtolower(substr($value, 2, $valueStart - 3));
          $value = substr($value, $valueStart, strlen($value) - $valueStart);

          $flags[$name] = $value;
        }
        else
        {
          $flags[strtolower(substr($value, 2, strlen($value) - 2))] = true;
        }
      }
      else
      {
        array_push($args, $value);
      }
    }
  }

  return array('args' => $args, 'flags' => $flags);
}

function abort($errorDescription)
{
  print $errorDescription ."\n";
  exit(1);
}

function abortIfDirectoryDoesNotExist($dir)
{
  if (!is_dir($dir))
  {
    abort('No "'. $dir .'" directory.');
  }
}

function mergeXliff(string $fromFile, string $toFile, bool $toAtoM)
{
  $domA = new DOMDocument();
  $domA->preserveWhiteSpace = false;
  $domA->formatOutput = true;
  $domA->load($fromFile);

  $domB = new DOMDocument();
  $domB->preserveWhiteSpace = false;
  $domB->formatOutput = true;
  $domB->load($toFile);

  if ($toAtoM) {
    $domB = mergeXliffDomToAtom($domA, $domB);
  }
  else {
    $domB = mergeXliffDomToWeblate($domA, $domB);
  }

  file_put_contents($toFile, $domB->saveXml($domB->documentElement));
}


function mergeXliffDomToAtom(DOMDocument $domA, DOMDocument $domB): DOMDocument
{
    // Elements in Weblate and in AtoM - overwrite AtoM trans-units to update 
    // translation. Ignore any trans-units in Weblate that no longer exist in AtoM.
    $sourcesA = $domA->getElementsByTagName('source');
    $sourcesB = $domB->getElementsByTagName('source');

    foreach ($sourcesA as $source) {
        $stringsA[] = $source->nodeValue;
    }

    foreach ($sourcesB as $source) {
        $stringsB[] = $source->nodeValue;
    }

    // If Weblate's XLIFF is empty (e.g. because all are unapproved)
    // then return the target dom unchanged.
    if (!isset($stringsA)) {
        return $domB;
    }

    $updateInB = array_intersect($stringsA, $stringsB);

    foreach($sourcesA as $sourceA) {
        if (in_array($sourceA->nodeValue, $updateInB)) {
            // This destination node needs updating. Get matching dest source node.
            foreach ($sourcesB as $sourceB) {
                if ($sourceB->nodeValue === $sourceA->nodeValue) {
                    // Get parent which should be a trans-unit.
                    $parentB = $sourceB->parentNode;
                    break;
                }
            }

            $parentA = $sourceA->parentNode;

            // Add trans-unit as child of domB body.
            $bodyB = $domB->getElementsByTagName('body')[0];
            $new = $domB->importNode($parentA, true);
            $bodyB->replaceChild($new, $parentB);
        }
    }

    return $domB;
}

function getStringFromSource(DOMNodeList $sources): string
{
  $strings = [];
  foreach ($sources as $source) {
    $strings[] = $source->nodeValue;
  }

  return implode("", $strings);
}

function mergeXliffDomToWeblate(DOMDocument $domA, DOMDocument $domB): DOMDocument
{
  // Elements in A AND B -> check source string and if different -> replace in B
  $transUnitsA = $domA->getElementsByTagName('trans-unit');
  $transUnitsB = $domB->getElementsByTagName('trans-unit');

  for ($i = 0; $i < count($transUnitsA); $i++) {
    if ($transUnitsA->item($i)->hasAttribute('id')) {
      $transUnitsA->item($i)->setIdAttribute('id', true);

      $idListA[] = $transUnitsA->item($i)->getAttribute('id');
    }
  }

  for ($i = 0; $i < count($transUnitsB); $i++) {
    if ($transUnitsB->item($i)->hasAttribute('id')) {
      $transUnitsB->item($i)->setIdAttribute('id', true);

      $idListB[] = $transUnitsB->item($i)->getAttribute('id');
    }
  }

  // Items in A that are also in B.
  $verifyInB = array_intersect($idListA, $idListB);

  // Use trans-unit's id attribute to identify strings. When one is
  // matched, compare the source string target and if different,
  // replace it in target.
  for ($i = 0; $i < count($transUnitsA); $i++) {
    $id = $transUnitsA->item($i)->getAttribute('id');

    if (in_array($id, $verifyInB)) {
      $node = $domB->getElementById($id);

      // Get 'source' string element from dom B trans-unit.
      $stringB = getStringFromSource($node->getElementsByTagName('source'));

      $stringA = getStringFromSource(
        $transUnitsA->item($i)->getElementsByTagName('source')
      );

      // Compare strings. If different, delete and replace trans-unit.
      if ($stringA !== $stringB) {
        // Remove trans-unit as child of domB body
        $bodyB = $domB->getElementsByTagName('body')[0];
        $bodyB->removeChild($node);

        // Add matching trans-unit from domA.
        $bodyB->appendChild($domB->importNode($transUnitsA->item($i), true));
      }
    }
  }

  // Elements in A but not in B -> copy to B.
  $copyToB = array_diff($idListA, $idListB);

  foreach ($copyToB as $id) {
    $node = $domA->getElementById($id);
    if (null !== $node) {
      $bodyB = $domB->getElementsByTagName('body')[0];

      // Add matching trans-unit from domA.
      $bodyB->appendChild($domB->importNode($node, true));
    }
  }

  // For elements in B not in A -> delete from B.

  // $idListB is stale here - it is missing items that were copied in
  // from A above. This does not matter for this comparison as these
  // items are already in A so are not removed.
  $removeFromB = array_diff($idListB, $idListA);

  foreach ($removeFromB as $id) {
    $node = $domB->getElementById($id);
    if (null !== $node) {
      $bodyB = $domB->getElementsByTagName('body')[0];
      $bodyB->removeChild($node);
    }
  }

  return $domB;
}

// Remove the 'translated' attribute from all trans-units in the XLIFF file.
// The 'translated' yes/no attribute attached to the trans-unit tag is no longer
// used.
function removeTranslatedAttribute($file)
{
  $dom = new DOMDocument();
  $dom->preserveWhiteSpace = false;
  $dom->formatOutput = true;
  $dom->load($file);

  // Remove 'translated' attribute
  $xpath = new DOMXPath($dom);
  $elements = $xpath->evaluate("//trans-unit");

  foreach ($elements as $element) {
    // Remove approved and translated attributes
    $element->removeAttribute('translated');
  }

  file_put_contents($file, $dom->saveXml($dom->documentElement));
}

function filterUnapprovedTransUnits($file)
{
  // Load XLIFF file into DOM document (tidying so whitespace is same as in AtoM)
  $dom = new DOMDocument();
  $dom->preserveWhiteSpace = false;
  $dom->formatOutput = true;

  $dom->load($file);

  $xpath = new DOMXPath($dom);
  $elements = $xpath->evaluate("//trans-unit");

  foreach ($elements as $element) {
    if (strtolower($element->getAttribute('approved')) != 'yes') {
        // Remove translation unit if it's not approved
        $element->parentNode->removeChild($element);
    }
    else {
      // Remove approved attribute
      $element->removeAttribute('approved');
    }
  }

  file_put_contents($file, $dom->saveXml($dom->documentElement));
}

function formatXliffForAtom($file, $options = array())
{
  // Load XLIFF file into DOM document (tidying so whitespace is same as in AtoM)
  $dom = new DOMDocument();
  $dom->preserveWhiteSpace = false;
  $dom->formatOutput = true;

  $dom->load($file);

  // Correct XML and DOCTYPE
  $newDom = new DOMDocument();
  $domImp = new DOMImplementation();
  $newDom->appendChild($domImp->createDocumentType('xliff PUBLIC "-//XLIFF//DTD XLIFF//EN" "http://www.oasis-open.org/committees/xliff/documents/xliff.dtd"'));

  // Create XML fragment with XLIFF element
  $xliffFragment = $newDom->createDocumentFragment();
  $xliffFragment->appendXml($dom->saveXml($dom->documentElement));

  $newDom->appendChild($xliffFragment);

  $newDom->save($file);
}

function formatXliffForWeblate($file, $setApproved = false)
{
  // Add 'approved' attributes
  addTransUnitAttributes($file, $setApproved);

  // Load XLIFF file into DOM document
  $dom = new DOMDocument();
  $dom->load($file);
  $dom->encoding = 'UTF-8';

  // Prepend XML in Weblate format
  $xml = "<?xml version='1.0' encoding='UTF-8'?>\n";
  $xml .= '<!DOCTYPE xliff PUBLIC "-//XLIFF//DTD XLIFF//EN" "http://www.oasis-open.org/committees/xliff/documents/xliff.dtd">'. "\n";
  $xml .= $dom->saveXml($dom->documentElement);

  file_put_contents($file, $xml);
}

function addTransUnitAttributes($file, $setApproved = false, $preserveIds = false)
{
  $changed = 0;

  $xml = simplexml_load_file($file);

  $elements = $xml->xpath("//trans-unit");

  foreach ($elements as $element)
  {
    if (!$preserveIds)
    {
      // Trans units extracted from source get numerical IDs: fix
      $id = (string) $element['id'];
      $source = $element->xpath('source')[0]->__toString();

      if ($id != sha1($source))
      {
        $element['id'] = sha1($source);

        $changed++;
      }
    }

    // Set "approved" and "translated" attributes so Weblate knows translation status
    if ($setApproved && count($element->xpath('source')) && count($element->xpath('target')) && !empty(trim($element->xpath('target')[0]->__toString())))
    {
      // Set "approved" attribute
      if (!isset($element['approved']) || strtolower($element['approved']) != 'yes')
      {
        if (!isset($element['approved']))
	      {
          $element->addAttribute('approved', 'yes');
	      }
	      else
	      {
          $element['approved'] = 'yes';
        }

        $changed++;
      }
    }
  }

  if ($changed)
  {
    $xml->asXml($file);
  }

  return $changed;
}
