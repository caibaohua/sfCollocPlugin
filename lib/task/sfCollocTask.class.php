<?php


class sfCollocTask extends sfBaseTask
{
  protected function configure()
  {
    $this->addOptions(array(
      new sfCommandOption('application', null, sfCommandOption::PARAMETER_REQUIRED, 'The application name'),
      new sfCommandOption('env', null, sfCommandOption::PARAMETER_REQUIRED, 'The environment'),
    ));

    $this->addArgument('url', sfCommandArgument::REQUIRED, 'The url of google i18n doc');

    $this->namespace           = 'colloc';
    $this->name                = 'i18n-extract';
    $this->briefDescription    = 'Manage the localizations using a Google Doc';
    $this->detailedDescription = <<<EOF
Manage the localizations using a Google Doc. Include the 3rd party PHP library colloc.php
EOF;
  }

  protected function execute($arguments = array(), $options = array())
  {
    $configuration = ProjectConfiguration::getApplicationConfiguration($options['application'], $options['env'], true);
    sfContext::createInstance($configuration);


    $COLLOC_PATH = sfConfig::get('sf_plugins_dir') . "/sfCollocPlugin/lib/Colloc/colloc.php";
    $GDOC_PATH   = $arguments['url'];
    $OUTPUT_TYPE = "001"; //#001 for json export

    if (!file_exists($COLLOC_PATH)) {
      throw new sfCommandException("Not find $COLLOC_PATH");
    }

    $OUTPUT_CACHE_FOLDER_NAME = sfConfig::get('sf_cache_dir').'/'.$options['application'].'/'.$options['env'];
    $cmd = "php $COLLOC_PATH \"$GDOC_PATH\" \"$OUTPUT_CACHE_FOLDER_NAME\" \"$OUTPUT_TYPE\"";
    $output = shell_exec($cmd);
    echo $output;

    $tmpfile = $OUTPUT_CACHE_FOLDER_NAME. '/stringsFromApp.json';
    if (!file_exists($tmpfile)) {
      throw new sfException("Not find stringsFromApp.json");
    }

    $strings = json_decode(file_get_contents($tmpfile), true);
    if (false === $strings) {
      throw new sfException("Parse stringsFromApp.json error");
    }

    if (count($strings) > 0) {
      foreach ($strings as $lang => $items) {
        $this->writeXLIFFXML($lang, $items);
      }
    }
  }

  protected function writeXLIFFXML($lang, $trans)
  {
    $dir = sfConfig::get('sf_app_i18n_dir');

    if (!file_exists($dir)) {
      $this->getFilesystem()->mkdirs($dir);
    }

    $filePath = $dir.'/messages.'.$lang.'.xml';

    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE xliff PUBLIC "-//XLIFF//DTD XLIFF//EN" "http://www.oasis-open.org/committees/xliff/documents/xliff.dtd">
<xliff version="1.0">
  <file original="global" source-language="en_US" datatype="plaintext">
    <body>
    </body>
  </file>
</xliff>
XML;

    $xml = new SimpleXMLElement($xml);

    $i=1;
    foreach ($trans as $source => $target) {
      $trans = $xml->file->body->addChild('trans-unit');
      $trans->addAttribute("id", $i);
      $trans->addChild("source", $source);
      $trans->addChild("target", htmlspecialchars($target));
      $i++;
    }

    // Format the output of XML
    $dom = new DOMDocument("1.0");
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $dom->loadXML($xml->asXML());

    if (false !== $dom->save($filePath)) {
      echo "\e[1;32m" . "Created file" . "\e[0m" .": $filePath \r\n";
    } else {
      echo "\e[1;31m" . "Failed to create file" . "\e[0m" .": $filePath \r\n";
    }
  }
}