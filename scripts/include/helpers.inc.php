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
        $flags[strtolower(substr($value, 2, strlen($value) - 2))] = true;
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

function setTransUnitAttributes($file)
{
  $changed = false;

  $xml = simplexml_load_file($file);

  $elements = $xml->xpath("//trans-unit");

  foreach ($elements as $element)
  {
    // Trans units extracted from source get numerical IDs: fix
    $id = (string) $element['id'];
    $source = $element->xpath('source')[0]->__toString();

    if ($id != sha1($source))
    {
      $element['id'] = sha1($source);
    }

    // Set "approved" and "translated" attributes so Weblate knows translation status
    if (count($element->xpath('source')) && count($element->xpath('target')) && !empty(trim($element->xpath('target')[0]->__toString())))
    {
      $element->addAttribute('approved', 'yes');
      $element->addAttribute('translated', 'yes');
      $changed = true;
    }
  }

  if ($changed)
  {
    $xml->asXml($file);
  }
}

function unsetTransUnitAttributes($file)
{
  $changed = false;

  $xml = simplexml_load_file($file);

  $elements = $xml->xpath("//trans-unit");

  foreach ($elements as $element)
  {
    // Set "approved" and "translated" attributes so Weblate knows translation status
    if (count($element->xpath('source')) && count($element->xpath('target')) && !empty(trim($element->xpath('target')[0]->__toString())))
    {
      $element['approved'] = '';
      $element['translated'] = '';
      $changed = true;
    }
  }

  if ($changed)
  {
    $xml->asXml($file);
  }
}

function formatXliffForWeblate($file)
{
  // Add 'approved' and 'translated' attributes
  setTransUnitAttributes($file);

  // Load XLIFF file into DOM document
  $dom = new DOMDocument();
  $dom->load($file);
  $dom->encoding = 'UTF-8';

  // Prepend XML in Weblate format
  $xml = "<?xml version='1.0' encoding='UTF-8'?>\n";
  #$xml = str_replace('"', "'", $newDome->saveXml());
  $xml .= '<!DOCTYPE xliff PUBLIC "-//XLIFF//DTD XLIFF//EN" "http://www.oasis-open.org/committees/xliff/documents/xliff.dtd">'. "\n";
  $xml .= $dom->saveXml($dom->documentElement);

  file_put_contents($file, $xml);
}

/*
function formatXliffForAtom($file)
{
  // Load XLIFF file into DOM document (tidying so whitespace is same as in AtoM)
  $dom = new DOMDocument();
  $dom->preserveWhiteSpace = false;
  $dom->formatOutput = true;

  $dom->load($file);

  // Remove 'approved' and 'translated' attributes
  $xpath = new DOMXPath($dom);
  $elements = $xpath->evaluate("//trans-unit");

  foreach ($elements as $element)
  {
    $element->removeAttribute('approved');
    $element->removeAttribute('translated');
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

function migrate($fromI18nDir, $toI18nDir, $options = array())
{
  // Make sure destination directory exists
  if (!is_dir($toI18nDir))
  { 
    abort('No "'. $toI18nDir .'" directory.');
  }

  // Cycle through each AtoM language directory
  print "Attempting to cycle through language directories in ". $fromI18nDir ."...\n";
  try
  {
    $dir = new DirectoryIterator($fromI18nDir);
  }
  catch(Exception $e)
  {
    abort('Unable to access '. $fromI18nDir);
  }

  $copyCount = 0;

  foreach ($dir as $fileinfo)
  {
    if (!$fileinfo->isDot()) {
      $langDir = $fileinfo->getFilename();

      // There's no need to migrate English as it doesn't need to be translated
      if (is_dir($fromI18nDir . DIRECTORY_SEPARATOR . $langDir) && $langDir != 'en')
      {
        // Each language's XLIFF file'll end up in a subdir of the i18n directory
        $destLangDir = $toI18nDir . DIRECTORY_SEPARATOR . $langDir;

        // Create destination language directory if it doesn't exist
        if (!is_dir($destLangDir))
        {
          mkdir($destLangDir);
          print "Created ". $destLangDir ."\n";
        }

        // Copy AtoM's XLIFF file into repo (replacing if a file already exists)
        $langFileRelativePath = $langDir . DIRECTORY_SEPARATOR . "messages.xml";
        $fromFile = $fromI18nDir . DIRECTORY_SEPARATOR . $langFileRelativePath;
        $toFile = $toI18nDir . DIRECTORY_SEPARATOR . $langFileRelativePath;
        copy($fromFile, $toFile);

        // Add approved and translated properties
        if (!empty($options['import']))
        {
          formatXliffForWeblate($toFile);
        }

        // Remove approved and translated properties
        if (!empty($options['export']))
        {
          formatXliffForAtom($toFile);
        }

        if (!empty($options['debug']))
        {
          print 'DEBUG: Updated '. $toFile ." (". filesize($toFile) ." bytes)\n";
        }

        $copyCount++;
      }
    }
  }

  return $copyCount;
}

$parsed = parseArgs($argv);
$args = $parsed['args'];
$flags = $parsed['flags'];

$fromDirectory = (isset($args[0])) ? $args[0] : null;
$toDirectory   = (isset($args[1])) ? $args[1] : null;

if (empty($fromDirectory) || empty($toDirectory))
{
  abort("Usage: ". basename(__FILE__) . " [--import|--export] <from directory> <to directory>\n");
}

if (empty($flags['import']) && empty($flags['export']))
{
  abort('Either the --import or --export flag should be used with this script.');
}

$copyCount = migrate($fromDirectory, $toDirectory, $flags);

if (!empty($flags['import']))
{
  print "Importing from AtoM... the 'approved' and 'translated' attributes were added for translated units.\n";
}
elseif (!empty($flags['export']))
{
  print "Exporting to AtoM... the 'approved' and 'translated' attributes were removed from translation units.\n";
}

print("Done: copied ". $copyCount ." XLIFF files.\n");
*/
