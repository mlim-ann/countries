<?php
require_once "vendor/autoload.php";

/**
 * Tools to convert countries in different formats
 * @author mledoze
 * @see https://github.com/mledoze/countries
 * @require PHP 5.4+
 */

/**
 * Interface Converter
 */
interface Converter {

	/**
	 * Convert into a new format
	 * @return string
	 */
	public function convert();

	/**
	 * Save the converted data to the disk
	 * @return mixed
	 */
	public function save();
}

/**
 * Class AbstractConverter
 */
abstract class AbstractConverter implements Converter {

	/** @var array */
	protected $aCountries;

	/**
	 * @var string path of the output directory
	 */
	private $sOutputDirectory;

	/** @var array defines the fields to keep */
	private $aFields;

	/**
	 * @param array $aCountries
	 */
	public function __construct(array $aCountries) {
		$this->aCountries = $aCountries;
	}

	/**
	 * Save the data to disk
	 * @param string $sOutputFile name of the output file
	 * @return int|bool
	 */
	public function save($sOutputFile = '') {
		if (empty($this->sOutputDirectory)) {
			$this->setDefaultOutputDirectory();
		}
		if (!is_dir($this->sOutputDirectory)) {
			mkdir($this->sOutputDirectory);
		}
		if (empty($sOutputFile)) {
			$sTempFile = date('Ymd-His', time()) . '-countries';
			$sOutputFile = $sTempFile;
		}

		// keep only the specified fields
		if (!empty($this->aFields)) {
			$aFields = $this->aFields;
			array_walk($this->aCountries, function (&$aCountry) use ($aFields) {
				$aCountry = array_intersect_key($aCountry, array_flip($aFields));
			});
		}
		return file_put_contents($this->sOutputDirectory . $sOutputFile, $this->convert());
	}

	/**
	 * Defines the fields to keep
	 * @param array $aFields
	 */
	public function setFields(array $aFields) {
		$this->aFields = $aFields;
	}

	/**
	 * Converts arrays to comma-separated strings
	 * @param array $aInput
	 * @param string $sGlue
	 * @return array
	 */
	protected function convertArrays(array &$aInput, $sGlue = ',') {
		return array_map(function ($value) use ($sGlue) {
			return is_array($value) ? $this->recursiveImplode($value, $sGlue) : $value;
		}, $aInput);
	}

	/**
	 * Set the default output directory
	 */
	private function setDefaultOutputDirectory() {
		$this->sOutputDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'dist' . DIRECTORY_SEPARATOR;
	}

	/**
	 * Recursively implode elements
	 * @param array $aInput
	 * @param $sGlue
	 * @return string the array recursively imploded with the glue
	 */
	private function recursiveImplode(array $aInput, $sGlue) {
		// remove empty strings from the array
		$aInput = array_filter($aInput, function ($entry) {
			return $entry !== '';
		});
		array_walk($aInput, function (&$value) use ($sGlue) {
			if (is_array($value)) {
				$value = $this->recursiveImplode($value, $sGlue);
			}
		});
		return implode($sGlue, $aInput);
	}
}

/**
 * Class AbstractJsonConverter
 */
abstract class AbstractJsonConverter extends AbstractConverter {

	/**
	 * Special case for empty arrays that should be encoded as empty JSON objects
	 */
	protected function processEmptyArrays() {
		array_walk($this->aCountries, function (&$aCountry) {
			if (empty($aCountry['languages'])) {
				$aCountry['languages'] = new stdClass();
			}
		});
	}
}

/**
 * Class JsonConverter
 */
class JsonConverter extends AbstractJsonConverter {

	/**
	 * @return string minified JSON, one country per line
	 */
	public function convert() {
		$this->processEmptyArrays();
		return preg_replace("@},{@", "},\n{", json_encode($this->aCountries) . "\n");
	}
}

/**
 * Class JsonConverterUnicode
 */
class JsonConverterUnicode extends JsonConverter {

	/**
	 * @return string minified JSON with unescaped characters
	 */
	public function convert() {
		$this->processEmptyArrays();
		return preg_replace("@},{@", "},\n{", json_encode($this->aCountries, JSON_UNESCAPED_UNICODE) . "\n");
	}
}

/**
 * Class YamlConverter
 */
class YamlConverter extends AbstractConverter {

	/**
	 * @return string data converted to Yaml
	 */
	public function convert() {
		$dumper = new \Symfony\Component\Yaml\Dumper();
		$inlineLevel = 1;

		return $dumper->dump($this->aCountries, $inlineLevel);
	}
}

/**
 * Class CsvConverter
 */
class CsvConverter extends AbstractConverter {

	/**
	 * @var
	 */
	private $sGlue = '";"';

	/**
	 * @var string
	 */
	private $sBody = '';

	/**
	 * @return string data converted into CSV
	 */
	public function convert() {
		array_walk($this->aCountries, [$this, 'processCountry']);
		$sHeaders = '"' . implode($this->sGlue, array_keys($this->aCountries[0])) . '"';
		return $sHeaders . "\n" . $this->sBody;
	}

	/**
	 * @return string
	 */
	public function getGlue() {
		return $this->sGlue;
	}

	/**
	 * @param string $sGlue
	 */
	public function setGlue($sGlue) {
		$this->sGlue = $sGlue;
	}

	/**
	 * Processes a country.
	 * @param $array
	 */
	private function processCountry(&$array) {
		$this->sBody .= '"' . implode($this->sGlue, $this->convertArrays($array)) . "\"\n";
	}
}

/**
 * Class XmlConverter
 */
class XmlConverter extends AbstractConverter {

	/** @var DOMDocument $oDom */
	private $oDom;

	/**
	 * @param array $aCountries
	 */
	public function __construct(array $aCountries) {
		$this->oDom = new DOMDocument('1.0', 'UTF-8');
		$this->formatOutput();
		$this->preserveWhiteSpace();
		$this->oDom->appendChild($this->oDom->createElement('countries'));
		parent::__construct($aCountries);
	}

	/**
	 * @return string data converted into XML
	 */
	public function convert() {
		array_walk($this->aCountries, array($this, 'processCountry'));
		return $this->oDom->saveXML();
	}

	/**
	 * @param bool $bFormatOutput
	 * @see \DOMDocument::$formatOutput
	 */
	public function formatOutput($bFormatOutput = true) {
		$this->oDom->formatOutput = $bFormatOutput;
	}

	/**
	 * @param bool $bPreserveWhiteSpace
	 * @see \DOMDocument::$preserveWhiteSpace
	 */
	public function preserveWhiteSpace($bPreserveWhiteSpace = false) {
		$this->oDom->preserveWhiteSpace = $bPreserveWhiteSpace;
	}

	/**
	 * @param $array
	 */
	private function processCountry(&$array) {
		$oCountryNode = $this->oDom->createElement('country');
		$array = $this->convertArrays($array);
		array_walk($array, function ($value, $key) use ($oCountryNode) {
			$oCountryNode->setAttribute($key, $value);
		});
		$this->oDom->documentElement->appendChild($oCountryNode);
	}
}

$aCountriesSrc = json_decode(file_get_contents('countries.json'), true);
(new JsonConverter($aCountriesSrc))->save('countries.json');
(new JsonConverterUnicode($aCountriesSrc))->save('countries-unescaped.json');
(new CsvConverter($aCountriesSrc))->save('countries.csv');
(new XmlConverter($aCountriesSrc))->save('countries.xml');
(new YamlConverter($aCountriesSrc))->save('countries.yml');