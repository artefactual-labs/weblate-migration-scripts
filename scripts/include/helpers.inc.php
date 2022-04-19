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

function mergeXliffAtomToWeblate(string $fromFile, string $toFile)
{
  $domA = new DOMDocument();
  $domA->preserveWhiteSpace = false;
  $domA->formatOutput = true;
  $domA->load($fromFile);

  $domB = new DOMDocument();
  $domB->preserveWhiteSpace = false;
  $domB->formatOutput = true;
  $domB->load($toFile);

  $result = mergeXliffDom($domA, $domB);

  file_put_contents($toFile, $result->saveXml($result->documentElement));
}

function mergeXliffDom(DOMDocument $domA, DOMDocument $domB): DOMDocument
{
  // Elements in A but not in B -> copy to B
  $sourcesA = $domA->getElementsByTagName('source');
  $sourcesB = $domB->getElementsByTagName('source');

  foreach ($sourcesA as $source) {
    $stringsA[] = $source->nodeValue;
  }

  foreach ($sourcesB as $source) {
    $stringsB[] = $source->nodeValue;
  }

  $copyToB = array_diff($stringsA, $stringsB);

  foreach($sourcesA as $source) {
    if (in_array($source->nodeValue, $copyToB)) {
      // get parent which should be a trans-unit
      $parent = $source->parentNode;
      // add trans-unit as child of domB body
      $bodyB = $domB->getElementsByTagName('body')[0];
      $bodyB->appendChild($domB->importNode( $parent, true ));
    }
  }

  // For elements in B not in A -> delete from B
  // Refresh string array
  foreach ($sourcesB as $source) {
    $stringsB[] = $source->nodeValue;
  }

  $removeFromB = array_diff($stringsB, $stringsA);

  foreach($sourcesB as $source) {
    if (in_array($source->nodeValue, $removeFromB)) {
      // get parent which should be a trans-unit
      $parent = $source->parentNode;
      // add trans-unit as child of domB body
      $bodyB = $domB->getElementsByTagName('body')[0];
      $bodyB->removeChild($parent);
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

function formatXliffForAtom($file, $options = array())
{
  // Load XLIFF file into DOM document (tidying so whitespace is same as in AtoM)
  $dom = new DOMDocument();
  $dom->preserveWhiteSpace = false;
  $dom->formatOutput = true;

  $dom->load($file);

  $xpath = new DOMXPath($dom);
  $elements = $xpath->evaluate("//trans-unit");

  foreach ($elements as $element) {
    if (!empty($options['approved']) && strtolower($element->getAttribute('approved')) != 'yes') {
      // Remove translation unit if it's not approved
      $element->parentNode->removeChild($element);
    }
    else {
      // Remove approved attribute
      $element->removeAttribute('approved');
    }
  }

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
