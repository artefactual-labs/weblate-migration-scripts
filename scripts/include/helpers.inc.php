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

function formatXliffForWeblate($file)
{
  // Add 'approved' and 'translated' attributes
  addTransUnitAttributes($file);

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

function addTransUnitAttributes($file)
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
