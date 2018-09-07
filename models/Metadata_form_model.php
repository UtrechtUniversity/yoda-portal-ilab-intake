<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');


class Metadata_form_model extends CI_Model
{

    var $CI = NULL;
    var $presentationElements = array(); // Main array representing all elements required to build the actual form (write / read )
    var $xsdElements = array(); // Xsd representation
    var $formMainFields = array(); // holding all info regarding form fields without the grouping information

    function __construct()
    {
        parent::__construct();
        $this->CI =& get_instance();

        $this->CI->load->model('filesystem');
    }

    /**
     * @param $rodsaccount
     * @param $config
     * @return array
     *
     * creates an array to be indexed by fully qualified element name.  eg 'creation_date'
     * This especially for subproperties eg 'creator_properties_pi' which is in fact a construct
     *
     * Flexdate translations require addition of three extra possibilities.
     * A specification of (YYYY, YYYY-MM, YYYY-MM-DD)
     * This as the value is not actuatlly saved as the name of the field.
     * But with extra info as how to validate it with the xsd
     * This has to be translated as well but these names are not known to corresponding formelements.
     *
     */
    public function getFormElementLabels($rodsaccount, $config)
    {
        $formGroupedElements = $this->loadFormElements($rodsaccount, $config['formelementsPath']);

        $groupNames = array();
        $elementLabels = array();
        foreach ($formGroupedElements['Group'] as $formElements) {

            foreach ($formElements as $key => $element) {
                if ($key == '@attributes') {
                    $groupNames[] = $element['name'];
                } else {
                    $this->_iterateElements($element, $key, $elementLabels, $key);
                }
            }
        }
        return $elementLabels;
    }

    /**
     * @param $element
     * @param $key
     * @param $elementLabels
     *
     * supporting function for getFormElementLabels
     * Adjusted so the leadproperty label is taken into account ($leadPropertyBase is passed throughout all iteration levels)
     */
    private function _iterateElements($element, $key, &$elementLabels, $leadPropertyBase, $level = 0)
    {
        $flexDateKeys = array('YYYY', 'YYYY_MM', 'YYYY_MM_DD'); // these postfixes have to correspond with the xsd definition

        if (isset($element['label']) AND !isset($element['@attributes']['class'])) {
            $elementLabels[$key] = $element['label'];
            if (isset($element['@attributes']['flexdate'])) {
                foreach ($flexDateKeys as $flexdatePostfix) {
                    $elementLabels[$key . '_' . $flexdatePostfix] = $element['label'];
                }
            }
            if ($level == 1) {
                $elementLabels[$leadPropertyBase] = $element['label'];
            } elseif ($level > 1) {
                $elementLabels[$key] = $elementLabels[$leadPropertyBase] . '-' . $element['label'];
                if($leadPropertyBase=='Embargo_End_Date') {
                    echo '<br> h2';
                }
            }
        } else { // not a label and therefore look at extra level.
            $level++;
            foreach ($element as $key2 => $element2) {
                $this->_iterateElements($element2, $key . '_' . $key2, $elementLabels, $leadPropertyBase, $level);
            }
        }
    }

// @todo: Obsolete nu met subroperties
    /**
     * @param $rodsaccount
     * @param $config
     * @return array
     *
     * returns all present form items in an array for the requested for form (in $config['elements'])
     */
    public function getFormElementsAsArray($rodsaccount, $config)
    {
        $formGroupedElements = $this->loadFormElements($rodsaccount, $config['formelementsPath']);

        // If there are multiple groups, the first should contain the number '0' as array-index .
        // Otherwise, this is the direct descriptive information (i.e. not an array form.
        // The software expects an array, so in the latter case should be an array)
        $formAllGroupedElements = array();
        foreach ($formGroupedElements['Group'] as $index => $array) {
            if ($index == '0') {
                // is the index of an array. So we have multiple groups.
                $formAllGroupedElements = $formGroupedElements['Group'];
            } else {
                $formAllGroupedElements[] = $formGroupedElements['Group']; // rewrite it as an indexable array as input for coming foreach
            }
            break;
        }

        $allElements = array();
        foreach ($formAllGroupedElements as $formElements) {
            foreach ($formElements as $key => $element) {
                if ($key != '@attributes') {
                    $allElements[] = $key;
                }
            }
        }
        return $allElements;
    }

    /**
     * @param $array
     * @return bool
     * structure like this:
     * [Name] =>
     * [Properties] => Array
     * (
     * [PI] =>
     * [PI_Type] =>
     * [Affiliation] =>
     * )
     */
//    public function arrayHoldsAnyData($array)
//    {
//        foreach ($array as $key => $value) {
//            if (!is_array($value)) {
//                if ($value) {
//                    return true;
//                }
//            } else {
//                foreach ($value as $key1 => $value1) {
//                    if (!is_array($value1)) {
//                        if ($value1) {
//                            return true;
//                        }
//                    } else {
//                        foreach ($value1 as $key2 => $value2) {
//                            if ($value2) {
//                                return true;
//                            }
//                        }
//                    }
//                }
//            }
//        }
//        return false;
//    }


    /**
     * @param $val
     * @return string
     *
     * determine which date type on the value that was passed by the user
     *
     * This function is written against the new flexible date picker
     * That allows for different date patters being YYYY, YYYY-MM, YYYY-MM-DD
     *
     * Simple interpretation - if wrong conclusions were drawn the xsd will guard it all
     * The value was erroneous in the first place then
     * There is no validation of the value involved
     */
    private function _determineDateTypeOnDateValue($val)
    {
        $dateTypes = array ('YYYY-MM-DD' => 'YYYY_MM_DD', 'YYYY' => 'YYYY', 'YYYY-MM' => 'YYYY_MM');

        $dateType = 'YYYY_MM_DD'; // default value

        $count = substr_count($val, '-');

        foreach($dateTypes as $type=>$typeName) {
            if ($count==substr_count($type,'-')) {
                $dateType = $typeName;
                break;
            }
        }

        return $dateType;
    }


    /**
     * @param $extraNodeDateType
     * @param $val
     * @return string

     Prevents wrong formats of date to be saved to
     */

    private function _correctDateFormat($extraNodeDateType, $val)
    {
        if (substr_count($val, '-') != substr_count($extraNodeDateType,'_')) {
            return $val;
        }

        $arDateParts = explode('-', $val);
        $arPartLengths = explode('_', $extraNodeDateType);

        for ($i=0; $i< count($arDateParts); $i++ ) {
            $arDateParts[$i] = str_repeat('0', strlen($arPartLengths[$i])-strlen($arDateParts[$i])) . $arDateParts[$i];
        }

        return implode('-', $arDateParts);
    }

    /**
     * @param $allFormMetadata - posted data from form
     * @return string
     *
     * Per form element that holds data create xml structure
     */
    private function _metadataToXmlString($allFormMetadata, $rodsaccount, $config)
    {
        $xsdPath = $config['xsdPath'];
        $this->xsdElements = $this->loadXsd($rodsaccount, $xsdPath);

        $formGroupedElements = $this->loadFormElements($rodsaccount, $config['formelementsPath']);
        if ($formGroupedElements === false) {
            return false;
        }

        // XML initialization
        $xml = new DOMDocument("1.0", "UTF-8");
        $xml->formatOutput = true;

        $xml_metadata = $xml->createElement("metadata");

        // Step through all fields
        // $this->formMainFields is filled in loadFormElements
        foreach($this->formMainFields  as $mainField=>$mainFieldData) { //formMainFields

            $isSubPropertyStructure = false;
            if (!$this->_isCompoundElement($mainFieldData) AND $this->_isSubpropertyStructure($mainFieldData)) {
                $isSubPropertyStructure = true;
            }

            // ElementData is posted data for the mainField at hand
            if (!isset($allFormMetadata[$mainField])) {
                return false;
            }
            $elementData = ($allFormMetadata[$mainField]);

            // Prepwork required for removal of entire empty compounds where necessary
            $arEmptyCompoundTestNames = array();
            $isCompoundInStruct = false;
            if (isset($mainFieldData['Properties'])) {
                $Properties = $mainFieldData['Properties'];

                foreach ($Properties as $pkey => $pval) { // For this moment think of only one compound present in subproperty-struct
                    $tempArray = array();
                    foreach ($pval as $itemName => $itemVal) {  //
                        if (substr($itemName, 0, 5) == '@attr' and isset($itemVal['class']) AND $itemVal['class']=='compound') {
                            $isCompoundInStruct = true;
                        } elseif ($isCompoundInStruct) {
                            $tempArray[] = $itemName;
                        }
                    }
                    if ($isCompoundInStruct) {
                        $arEmptyCompoundTestNames[$pkey] = $tempArray;
                    }
                }
            }

            // $allFormMetadata[$mainField]
            $topLevelData = array();
            if (!is_numeric(key($elementData))) {
                $topLevelData[] = $elementData;
            } else {
                $topLevelData = $elementData;
            }


            foreach ($topLevelData as $key => $val) { // per $val => entire structure to be added to $elementName
                $temp = array();
                if (!(is_array($val) AND is_numeric(key($val)))) {
                    // enumerated structures where we only need the structure itself.
                    $temp[] = $val;
                } else {
                    $temp = $val;
                }

                foreach ($temp as $elementInfo) { // step through each instance of each element

                    // Test for and remove fully empty structures as they intervene with saving the xml structure
                    if ($isSubPropertyStructure AND $arEmptyCompoundTestNames) { // can we test compound values in the subproperty-struct?

                        foreach ($arEmptyCompoundTestNames as $compoundKeyName => $compoundFieldNames) { // shoould be either 0 or 1 loop

                            $isEnumerated = is_numeric(key($elementInfo['Properties'][$compoundKeyName]));

                            if ($isEnumerated) {
                                $testArray = $elementInfo['Properties'][$compoundKeyName];  // base to test whether all compound elements are are empty

                                foreach ($testArray as $idTest => $testValues) {
                                    $isFilledCompound = false;

                                    foreach ($compoundFieldNames as $cfName) {
                                        //echo '<br>' . $compoundKeyName . '-' . $cfName;
                                        if ($testArray[$idTest][$cfName]) {
                                            $isFilledCompound = true; // at least one value
                                            break;
                                        }
                                    }

                                    if (!$isFilledCompound) {
                                        unset($elementInfo['Properties'][$compoundKeyName][$idTest]);
                                    }
                                }
                            }
                        }
                    }

                    if ($isSubPropertyStructure) {
                        // first element of lead-subproperty struct must have a value.
                        // Per posted data structure for this element, check whether the lead element holds a value.
                        // If not, don't write the data (which only consists of subproperty data) to the xml

                        if (reset($elementInfo) == '') {
                            break;
                        }
                    }

                    $xml_item = $xml->createElement($mainField); // base element

                    $level = 0; // for deletion handling - deeper levels (>0)can always be deleted (i.e. not written to file when empty value)
                    $metaStructureXML = $this->_xmlMetaStructure($xml, $elementInfo, $xml_item, $level);

                    if ($metaStructureXML['anyDataPresent']) {
                        $xml_metadata->appendChild($metaStructureXML['xmlParentElement']);
                    }
                }
            }
        }

        $xml->appendChild($xml_metadata);

        return $xml->saveXML();
    }


    //$topLevelName - for deletion purpose (Sub info can always be deleted (i.e. not added)
    /**
     * @param $xmlMain
     * @param $arMetadata
     * @param $xmlParentElement
     * @param $level
     * @param bool $anyDataPresent
     * @param string $totalTagName - for indexing in $xsdElements to find out whether a field is op type xs:date
     * @return array
     */
    private function _xmlMetaStructure($xmlMain, $arMetadata, $xmlParentElement, $level, $anyDataPresent = false, $totalTagName='')
    {
        $doAddThisLevel = true;

        $arrayMeta = array();
        if (!is_array($arMetadata)) {
            $arrayMeta[] = $arMetadata;
        } else {
            $arrayMeta = $arMetadata;
        }

        if (!$totalTagName) {
            $totalTagName = $xmlParentElement->tagName;
        }
        else {
            $totalTagName = $totalTagName . '_' . $xmlParentElement->tagName;
        }

        foreach ($arrayMeta as $key => $val) {
            if (!is_array($val) AND is_numeric($key)) {
                //return $xmlParentElement;
                ///een val is altijd het eind ding van een element.
                // Als er geen waarde is, moet dus ook het element worden verwijderd
                if ($val != '') {
                    //echo '<br>total tag: ' . $totalTagName;
                    if ($this->xsdElements[$totalTagName]['type'] == 'xs:date') {
                        // the date requires years to be added YYYY
                        // 2017-10-12 -> length = 10
                        // years before 1000 can be added by the datepicker but are formatted like: 900-12-25
                        $val = str_repeat('0', 10-strlen($val)) . $val; // add preceiding 0's -> 0900-12-25
                    }

                    // Find out whether is a flexdate:
                    $arrayForDatePattern = array();
                    $counter = 0;
                    foreach ($this->xsdElements[$totalTagName]['tagNameRouting'] as $tagKey) {
                        if (!$counter) {
                            $arrayForDatePattern = $this->formMainFields[$tagKey];
                        }
                        else {
                            $arrayForDatePattern = $arrayForDatePattern[$tagKey];
                        }
                        $counter++;
                    }
                    if (isset( $arrayForDatePattern['@attributes']['flexdate'] )) { // requires extra level determining the type of date that was passed
                        $extraNodeDateType = $this->_determineDateTypeOnDateValue($val);

                        $dateElementTagName = $this->xsdElements[$totalTagName]['tagNameRouting'][count($this->xsdElements[$totalTagName]['tagNameRouting'])-1];
                        $xmlNodeDateType =  $xmlMain->createElement($dateElementTagName . '_' . $extraNodeDateType); // construct name on basis of date element as well as actual type

                        $dateVal = $this->_correctDateFormat($extraNodeDateType, $val);

                        $xmlNodeDateType->appendChild($xmlMain->createTextNode($dateVal));
                        $xmlParentElement->appendChild($xmlNodeDateType);
                    }
                    else { // normal addition of value to the same level
                        $xmlParentElement->appendChild($xmlMain->createTextNode($val));
                    }

                    $anyDataPresent = true;
                } else {
                    $doAddThisLevel = false;
                }
            } else {
                $level++;
                // $val kan hier multi zijn
                $arraySubLevels = array();
                if (!is_numeric(key($val))) { // if not enumerated
                    $arraySubLevels[] = $val;
                } else {
                    $arraySubLevels = $val;
                }
                foreach ($arraySubLevels as $key2 => $val2) {
                    $xmlElement = $xmlMain->createElement($key);

                    // Deze aanroep gebeurt in het kader van $xmlElement / $key.
                    $structInfo = $this->_xmlMetaStructure($xmlMain, $val2, $xmlElement, $level, $anyDataPresent, $totalTagName);

                    // Add (entire) srtructure or 1 value
                    // Het element wordt hier al geappend zonder dat zeker is of dit moet (en dan komt ie als lege tag in yoda-metadata.xml)
                    if ($structInfo['doAddThisLevel'] AND is_object($structInfo['xmlParentElement']->firstChild)) {
                        $xmlParentElement->appendChild($structInfo['xmlParentElement']);
                    }
                    $anyDataPresent = $structInfo['anyDataPresent'];
                }
            }
        }
        return array('xmlParentElement' => $xmlParentElement,
            'anyDataPresent' => $anyDataPresent,
            'doAddThisLevel' => $doAddThisLevel
        );
    }

// --------------------- NEW STUFF

    private function _isCompoundStructure($xsdElements, $parentFullRoute, $arrayParentRoute)
    {
        // find the element in the XSD and check whether next field is structurally deeper.
        // if so, current element is a compound structure.
        $found = false;

        foreach ($xsdElements as $keyElement => $element) {
            if (!$found) {
                $found = ($element['tagNameRouting'] === $arrayParentRoute);
            }
            else {
                // check whether current element in loop is a child of the item passed as a parameter
                $possibleChildRoute = implode('-', $element['tagNameRouting']);
                return (strpos($parentFullRoute, $possibleChildRoute) == 0);
            }
        }
        return false;
    }

    // Afwikkeling van 1 gehele subproperty structuur.
    private function _constructSubPropertyStruct($xml, $xsdElements, $key, $val)
    {
        $valueArray = $val; // specifically to be able to retrieve values based on nodeName

        $structElement = array();
        $level = 0;
        $structHasValues = false;
        $loopToNextOnSameLevel = false;
        $baseStructureRoute = '';

        // Trick to gather the engtire structure for a given subperoperty structure
        foreach ($xsdElements as $keyElement => $element) {

            // Step through all elements of a structure with $key at highest level
            if ($element['tagNameRouting'][0] == $key) { // Rule out other fields

                $currentFullRoute = implode('-',$element['tagNameRouting']); // eg Creator-Properties-Person_Identifier

                if (!$loopToNextOnSameLevel OR ($currentFullRoute==$baseStructureRoute)) {
                    // als doorgestapt moet worden naar zelfde of lager level omdat compound al bepaalde velden heeft afgewikkeld.

                    $nodeName = $element['tagNameRouting'][count($element['tagNameRouting']) - 1];

                    if ($element['type'] == 'openTag') {                           // Step into a structure
                        $structElement[$level] = $xml->createElement($nodeName);

                        if ($nodeName != 'Properties' AND $level > 0) {
                            $valueArray = $val[$nodeName];

                            $baseStructureRoute = $currentFullRoute;
                            /// Are there deeper levels???? how to know????
                            if ($this->_isCompoundStructure($xsdElements, $currentFullRoute, $element['tagNameRouting'])) {

                                foreach ($valueArray as $nodeValueCombination) {
                                    $xmlCompound = $xml->createElement($nodeName);
                                    $xmlCompound = $this->_constructCompoundStruct($xml, $xmlCompound, $nodeValueCombination);
                                    $structElement[$level - 1]->appendChild($xmlCompound);
                                }
                                // als je hier bent geweest, moet je net zolang loopen via foreach
                                // tot je weer bij een element op zelfde niveau komt.
                                // Of lager

                                $loopToNextOnSameLevel = true;
                                $baseStructureRoute = $currentFullRoute;

                                $level--;  // KLOPT DIT?? to prevent the level from actually going up. This element (including subs) has been fully completed
                                //break;
                            }
                        }
                        $level++; /// ALLEEN als level nog niet complete is!!!! DIt dus als niet echt een Compound maar Single multiple element betreft

                    } else { // hier wordt text toegevoegd aan node

//                        print_r($valueArray[$nodeName]);

                        if (is_array($valueArray[$nodeName])) {
                            // add each value as a separate node
                            foreach ($valueArray[$nodeName] as $nodeValue) {
                                $valueElement = $xml->createElement($nodeName);

                                // het zoeken in de val moet niveau afhankelijk worden.
                                $valueElement->appendChild($xml->createTextNode($nodeValue));

                                $structElement[$level - 1]->appendChild($valueElement);

                                if (isset($valueArray[$nodeName]) AND $valueArray[$nodeName] != '' AND !is_array($valueArray[$nodeName])) {
                                    $structHasValues = true;
                                }
                            }
                        } else {
                            $valueElement = $xml->createElement($nodeName);

                            // het zoeken in de val moet niveau afhankelijk worden.
                            $valueElement->appendChild($xml->createTextNode($valueArray[$nodeName]));

                            $structElement[$level - 1]->appendChild($valueElement);

                            if (isset($valueArray[$nodeName]) AND $valueArray[$nodeName] != '' AND !is_array($valueArray[$nodeName])) {
                                $structHasValues = true;
                            }
                        }
                    }
                }
            }
        }

        // Build one element structure out of its constructing sub nodes

        if (!$structHasValues) { // no data present, no struct will be added
            return false;
        }

        $index = $level-1;
        while($index>0) {
            $structElement[$index-1]->appendChild($structElement[$index]);
            $index--;
        }

        return $structElement[0];  // return entire structure to be added
    }

    /*
     * Add all subnodes to compound structure
     * <parentNode>
     *    <var>bla</var>
     * </parentNode>
     */
    private function _constructCompoundStruct($xml, $xmlParentNode, $arrayCompoundItems)
    {
        foreach($arrayCompoundItems as $subNode=>$subNodeValue) {
            $xmlNode = $xml->createElement($subNode);
            $xmlNode->appendChild($xml->createTextNode($subNodeValue));
            $xmlParentNode->appendChild($xmlNode);
        }
        // Return the adjust parent node
        return $xmlParentNode;
    }
// ------------------- EOF supporting functions XSD based post processing
// ----------------    SOF supporting functions JSONS based post processing

    /**
     * @param $rodsaccount
     * @param $config
     *
     * Handles the posted information of a yoda form and puts the values, after escaping, in .yoda-metadata.xml
     * The config holds the correct paths to form definitions and .yoda-metadata.xml
     *
     * NO VALIDATION OF DATA IS PERFORMED IN ANY WAY
     */
    public function processPost($rodsaccount, $config)
    {
        $arrayPost = $this->CI->input->post();
        $formReceivedData = json_decode($arrayPost['formData'], true);

        // formData now contains info of descriptive groups.
        // These must be excluded first for ease of use within code
        $formData = array();
        foreach($formReceivedData as $group=>$realFormData) {
            #first level to be skipped as is descriptive
            foreach($realFormData as $key => $val  ) {
                $formData[$key] = $val;
            }
        }

        # $this->Metadata_form_model->processPost($rodsaccount, $formConfig);
        $xsdPath = $config['xsdPath'];
        $jsonsElements = $this->_loadJSONS($rodsaccount, $xsdPath);

        $xml = new DOMDocument("1.0", "UTF-8");
        $xml->formatOutput = true;

        $xml_metadata = $xml->createElement("metadata");

        foreach ($jsonsElements['properties'] as $groupName => $formElements) {
            foreach ($formElements['properties'] as $mainElement => $element) {
                if (isset($formData[$mainElement])) {
                    if (!isset($element['type'])) {  //No structure single element
                        $xmlMainElement = $xml->createElement($mainElement);
                        $xmlMainElement->appendChild($xml->createTextNode($formData[$mainElement]));

                        $xml_metadata->appendChild($xmlMainElement);
                    }
                    else {
                        $structObject = array();
                        $extraInfo = '';

                        if ($element['type'] == 'object') {   // SINGLGE STRUCT OP FIRST LEVEL
                            $structObject = $element;

                            if ($structObject['yoda:structure'] == 'compound') { // heeft altijd een compound signifying element nodig
                                $xmlMainElement = $xml->createElement($mainElement);
                                $anyValueFound = false;
                                foreach ($structObject['properties'] as $compoundElementKey => $compoundElementInfo) {
                                    $compoundValue = '';
                                    if (isset($formData[$mainElement][$compoundElementKey])) {
                                        $compoundValue = $formData[$mainElement][$compoundElementKey];
                                        $anyValueFound = true;
                                    }

                                    $xmlCompoundElement = $xml->createElement($compoundElementKey);
                                    $xmlCompoundElement->appendChild($xml->createTextNode($compoundValue));
                                    $xmlMainElement->appendChild($xmlCompoundElement);
                                }
                                if ($anyValueFound) {
                                    $xml_metadata->appendChild($xmlMainElement);
                                }
                            }
                            elseif($structObject['yoda:structure'] == 'subproperties'){  // SINGLE subproperty struct is not present at the moment in the schema

                            }
                        }
                        // MULTIPLE
                        elseif ($element['type'] == 'array') {
                            if (!(isset($element['items']['type']) and $element['items']['type'] == 'object')) {
                                // multiple non structured element
                                // So loop through data now

                                foreach($formData[$mainElement] as $value) {
                                    if ($value) {
                                        $xmlMainElement = $xml->createElement($mainElement);
                                        $xmlMainElement->appendChild($xml->createTextNode($value));

                                        $xml_metadata->appendChild($xmlMainElement);
                                    }
                                }
                            }
                            // multiple structures
                            else {
                                $structObject = $element['items'];
                                if ($structObject['yoda:structure'] == 'subproperties') {
                                    foreach ($formData[$mainElement] as $subPropertyStructData) {
                                        $xmlMainElement = $xml->createElement($mainElement);
                                        $index = 0; // to distinguish between lead and sub
                                        foreach ($structObject['properties'] as $subPropertyElementKey => $subPropertyElementInfo) {

                                            // Step through object structure
                                            if ($index==0) { // Lead part of structure - ALWAYS SINGLE VALUE!!
                                                $xmlLeadElement = $xml->createElement($subPropertyElementKey);
                                                $leadData = isset($subPropertyStructData[$subPropertyElementKey])? $subPropertyStructData[$subPropertyElementKey] : '';
                                                $xmlLeadElement->appendChild($xml->createTextNode($leadData));  // @TODO - get correct lead value

                                                $xmlMainElement->appendChild($xmlLeadElement);
                                            }
                                            elseif($index==1) { // Start of subproperty part.

                                                $xmlProperties = $xml->createElement('Properties');

                                                // Subproperty part of structure --
                                                // This is the first line
                                                // NEVER compound on first subprop line so take shortcut here.

                                                $values = array();
                                                if (!isset($subPropertyElementInfo['type'])) {
                                                    $values[0] = isset($subPropertyStructData[$subPropertyElementKey])? $subPropertyStructData[$subPropertyElementKey] : '';
                                                }
                                                else if ($subPropertyElementInfo['type']=='array') {
                                                    $values = $subPropertyStructData[$subPropertyElementKey];
                                                }

                                                foreach($values as $value) {
                                                    $xmlSubElement = $xml->createElement($subPropertyElementKey);

                                                    //$subData = isset($subPropertyStructData[$subPropertyElementKey])? $subPropertyStructData[$subPropertyElementKey] : '';
                                                    $xmlSubElement->appendChild($xml->createTextNode($value));  // @TODO - get correct lead value

                                                    $xmlProperties->appendChild($xmlSubElement);
                                                }
                                            }
                                            else {  // next lines after first in subproperty part
                                                if (!isset($subPropertyElementInfo['type'])) {
                                                    echo 'SINGLE ITEM';  ///KOMT  NU NIET VOOR VOOR SUBPROPERTIES
                                                }
                                                elseif ($subPropertyElementInfo['type']=='array') {
                                                    if (!isset($subPropertyElementInfo['items']['type'])) {
                                                        foreach($subPropertyStructData[$subPropertyElementKey] as $value) {
                                                            $xmlSubElement = $xml->createElement($subPropertyElementKey);
                                                            $xmlSubElement->appendChild($xml->createTextNode($value));
                                                            $xmlProperties->appendChild($xmlSubElement);
                                                        }
                                                    }
                                                    else {
                                                        foreach($subPropertyStructData[$subPropertyElementKey] as $data) {
                                                            $xmlSubElement = $xml->createElement($subPropertyElementKey);
                                                            foreach ($subPropertyElementInfo['items']['properties'] as $subCompoundKey => $subVal) {
                                                                $subData = isset($data[$subCompoundKey]) ? $data[$subCompoundKey] : '';

                                                                //$xmlSubElement->appendChild($xml->createTextNode($subData));
                                                                $xmlSubCompound = $xml->createElement($subCompoundKey);
                                                                $xmlSubCompound->appendChild($xml->createTextNode($subData));
                                                                $xmlSubElement->appendChild($xmlSubCompound);
                                                            }

                                                            $xmlProperties->appendChild($xmlSubElement);  // xmlProperties wordt geinitieerd in vorige stap
                                                        }
                                                    }
                                                }
                                                elseif ($subPropertyElementInfo['yoda:structure']=='compound'){
                                                    $xmlSubElement = $xml->createElement($subPropertyElementKey);

                                                    foreach($subPropertyElementInfo['properties'] as $subCompoundKey => $subVal) {
                                                        $subData = isset($subPropertyStructData[$subPropertyElementKey][$subCompoundKey])?
                                                                            $subPropertyStructData[$subPropertyElementKey][$subCompoundKey] : '';

                                                        $xmlSubCompound = $xml->createElement($subCompoundKey);
                                                        $xmlSubCompound->appendChild($xml->createTextNode($subData));
                                                        $xmlSubElement->appendChild($xmlSubCompound);
                                                    }

                                                    $xmlProperties->appendChild($xmlSubElement);  // xmlProperties wordt geinitieerd in vorige stap
                                                }
                                            }

                                            $index++;
                                        }
                                        // add the entire structure to the main element
                                        $xmlMainElement->appendChild($xmlProperties);
                                        $xml_metadata->appendChild($xmlMainElement);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }


        $xml->appendChild($xml_metadata);

        $xmlString = $xml->saveXML();

        print_r($xmlString);

        $this->CI->filesystem->writeXml($rodsaccount, $config['metadataXmlPath'], $xmlString);

        exit;


//        echo '---------------------------<br>';
//        print_r($xsdElements);
//        exit;

        // XML initialization

    }



    /**
     * @param $rodsaccount
     * @param $config
     *
     * Handles the posted information of a yoda form and puts the values, after escaping, in .yoda-metadata.xml
     * The config holds the correct paths to form definitions and .yoda-metadata.xml
     *
     * NO VALIDATION OF DATA IS PERFORMED IN ANY WAY
     */
    public function processPostXsdBased($rodsaccount, $config)
    {
        $arrayPost = $this->CI->input->post();
        $formData = json_decode($arrayPost['formData'], true);

        print_r($formData);

        # $this->Metadata_form_model->processPost($rodsaccount, $formConfig);
        $xsdPath = $config['xsdPath'];
        $xsdElements = $this->Metadata_form_model->loadXsd($rodsaccount, $xsdPath);

//        echo '---------------------------<br>';
//        print_r($xsdElements);
//        exit;

        // XML initialization
        $xml = new DOMDocument("1.0", "UTF-8");
        $xml->formatOutput = true;

        $xml_metadata = $xml->createElement("metadata");

        foreach($formData as $group=>$realFormData) {
            #first level to be skipped as is descriptive
            foreach($realFormData as $key => $val  ) {
                # Determine whether this field is a subproperty structure - only on highes level
                $isSubPropStructure = isset($xsdElements[$key . '_Properties']);

                $addCompoundStructure = false;

                $xmlBaseElement = $xml->createElement($key);

                if ($isSubPropStructure AND is_array($val)) {
                    // Single or multiple presence?
                     $subPropStructs = (!isset($val[0]) ? array(0=>$val): $val );

                    // Add 1 or many Sub property structs to main xml node
                    foreach($subPropStructs as $valSingle) {
                        $newNode = $this->_constructSubPropertyStruct($xml, $xsdElements, $key, $valSingle);
                        if ($newNode!==false) {
                            $xml_metadata->appendChild($newNode);
                        }
                    }
                }
                elseif (!is_array($val)) {
                    $xmlBaseElement->appendChild($xml->createTextNode($val));
                    $xml_metadata->appendChild($xmlBaseElement);
                }
                else {
                    # Structure can either be:
                    # Repetition of same element
                    # Structure of elements
                    foreach($val as $keyLevel_1=>$valLevel_1) {
                        if (is_numeric($keyLevel_1)) {   //VAL moet string zijn, niet array dus
                            $xmlMainNode = $xml->createElement($key);

                            if(!is_array($valLevel_1)) { // NORMAL TOPLEVEL FIELD
                                $xmlMainNode->appendChild($xml->createTextNode($valLevel_1));
                            }
                            else { // COMPOUND-COMPOSITE FIELD - build structure  HIER KOMEN DE MULTPLIE STRUCTS
                                $xmlMainNode = $this->_constructCompoundStruct($xml, $xmlMainNode, $valLevel_1);
                            }
                            $xml_metadata->appendChild($xmlMainNode);
                        }
                        else {
                            // COMPOUND structure - DIT IS OP TOPLEVEL. Elke loop stap wordt er een subnode toegevoegd.
                            $addCompoundStructure = true;
                            $xmlElement = $xml->createElement($keyLevel_1);
                            $xmlElement->appendChild($xml->createTextNode($valLevel_1));
                            $xmlBaseElement->appendChild($xmlElement);
                        }

                        if ($addCompoundStructure) {
                            $xml_metadata->appendChild($xmlBaseElement);
                        }
                    }
                }
            }
        }

        $xml->appendChild($xml_metadata);

        $xmlString = $xml->saveXML();

        print_r($xmlString);
        $this->CI->filesystem->writeXml($rodsaccount, $config['metadataXmlPath'], $xmlString);

        return true;


        //exit;

        // OLD
        $this->CI->filesystem->writeXml($rodsaccount, $config['metadataXmlPath'], $xmlString);

        // OLD


        if (isset($allFormMetadata['vault_submission'])) { // clean up: this is extra input in the posted data that should not be handled as being metadata
            unset($allFormMetadata['vault_submission']);
        }

        $xmlString = $this->_metadataToXmlString($allFormMetadata, $rodsaccount, $config);

//        exit;

        $this->CI->filesystem->writeXml($rodsaccount, $config['metadataXmlPath'], $xmlString);
    }

    /**
     * @param array $array
     * @return array
     *
     * Created unemarated array of a possibly associative array
     * For consistent value processing
     */
    private function _enumerateArray(array $array)
    {
        if (!is_numeric(key($array))) {
            $enumeratedArray = array();
            $enumeratedArray[] = $array;
            return $enumeratedArray;
        }
        return $array;
    }

    private function _isGeoLocation($element)
    {
        return (isset($element['@attributes']['geoLocation']));
    }


    /**
     * @param $element
     * @return bool
     *
     * Determine whether given element actually is a compound element and consists of several element in itself
     */
    private function _isCompoundElement($element)
    {
        return (isset($element['@attributes']['class']) AND strtoupper($element['@attributes']['class']) == 'COMPOUND');
    }

    /**
     * @param $compoundElement
     * @return int
     *
     * Get the count of form elements within compoound field represented in $element
     * label, help and @attributes are not allowed to be counted as are not elements!
     */
    private function _getCountOfElementsInCompound($compoundElement)
    {
        $exclude = array('label', 'help', '@attributes');
        $count=0;
        foreach($compoundElement as $elementName=>$elementData) {
            if (!in_array($elementName, $exclude)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * @param $element
     * @return bool
     *
     * Determine whether given element is a subroperty structure - i.e. lead element and N subproperties
     */
    private function _isSubpropertyStructure($element)
    {
        return (!isset($element['label']));
    }

    // Combination of Lead & subproperties => a combined field can also be a subproperty

    // Pre conditions for lead&subproperties:
    // 1 lead element
    // 1 property element designating properties
    // subproperties can be variable amounts and types (even compound element)

    // General rule: if a subproperty is present, the lead element has to be present as well

    private function _addLeadAndSubpropertyElements($config, $groupName, $key, $element, $structValueArray, $xsdElements)
    {
        $writeMode = true;
        if ($config['userType'] == 'reader' || $config['userType'] == 'none') {
            $writeMode = false; // Distinnction made as readers, in case of no xml-file being present, should  NOT get default values
        }

        $elementCounterForFrontEnd = 0; // to be able to distinghuis structures and add to key

        // Per struct (at least once - otherwise N times) create the lead and subproperty elements
        foreach ($structValueArray as $structValues) {

            // MAIN LOOP TO SETUP A COMPLETE SUBPROPERTY STRUCTURE
            foreach ($element as $id => $em) {
                // $elements now holds an array of formelements - these are subproperties
                $subKey = $key . '_' . $id;

                if (isset($em['label'])) {   // LEAD ELEMENT OF A STRUCT

                    //$id can be used straight as lead is always single value
                    $subKeyValue = $structValues[$id];

                    // Use toplevel here as that defines multiplicity for a structure (is in fact not an element
                    $multipleAllowed = $this->_isElementMultipleAllowed($xsdElements[$key]);

                    $keyCounterInfix = '';

                    // @todo - zijn er meerdere waarden??? dan is dat fout => afbreken
                    if (!$multipleAllowed) {
//                    if(count($structValueArray)>1) {
//                        return false;
//                    }

                    } else {
                        $keyCounterInfix = '[' . $elementCounterForFrontEnd . ']'; // Add counter to key for controller array creation for frontend
                    }
                    // frontend value is the value that will be presented in the data field
                    // If no metadata-file present, it will fall back to its default ONLY of in writable mode (i.e NO READER)
                    $frontendValue = (isset($em['default']) AND $writeMode) ? $em['default'] : null;

                    if ($config['hasMetadataXml'] == 'true' || $config['hasMetadataXml'] == 'yes') { // the value in the file supersedes default @todo!!!????????? hoe zit dit??
                        $frontendValue = $subKeyValue;
                    }

                    $subPropArray = array(
                        'subPropertiesRole' => 'subPropertyStartStructure',
                        'subPropertiesBase' => $key,
                        'subPropertiesStructID' => $multipleAllowed ? $elementCounterForFrontEnd : -1
                    );

                    $this->presentationElements[$groupName][] =
                        $this->_addPresentationElement(
                            $config, $xsdElements[$subKey], $em, $key . $keyCounterInfix . '[' . $id . ']', $frontendValue, $multipleAllowed, $subPropArray);

                    //  Add start tag
                    $this->presentationElements[$groupName][] =
                        $this->_addPresentationElement(
                            $config, array('type' => 'structSubPropertiesOpen'), $em, $key . $keyCounterInfix . '[' . $id . ']', '', $multipleAllowed, $subPropArray);

                } else { // STEP THROUGH EACH SUB PROPERTY
                    foreach ($em as $propertyKey => $propertyElement) {
                        $subKey = $key . '_' . $id . '_' . $propertyKey;
                        $subPropArray = array(
                            'subPropertiesRole' => 'subProperty',
                            'subPropertiesBase' => $key,
                            'subPropertiesStructID' => $multipleAllowed ? $elementCounterForFrontEnd : -1
                        );

                        if ($this->_isCompoundElement($propertyElement)) { //isset($propertyElement['combined'])) {

                            $arRouting = $xsdElements[$subKey]['tagNameRouting'];

                            $subKeyValue = $structValues[$arRouting[1]][$arRouting[2]];

                            $subKeyValue = $this->_enumerateArray($subKeyValue);


                            //$this->_addCompoundElement($config, $groupName, $key, $element, $formData, $xsdElements);
                            // todo:: check value array -> is this what is required here???

                            $offsetKeyForFrontEnd = $key . $keyCounterInfix . '[' . $id . ']' . '[' . $propertyKey . ']';

                            // trap incorrect formatting
                            if (!$this->_addCompoundElement($config, $groupName, $subKey, $propertyElement, $subKeyValue, $xsdElements,
                                $offsetKeyForFrontEnd, $subPropArray)
                            ) {
                                return false;
                            }
                        } else {
                            $arRouting = $xsdElements[$subKey]['tagNameRouting'];

                            // we know this is the 2nd(1 in tagNameRoute) and 3rd(2 in tagNameRoute) level so no real analysing required
                            $subKeyValue = $structValues[$arRouting[1]][$arRouting[2]];

                            $subKeyValue = $this->_enumerateArray($subKeyValue);

                            $multipleAllowed = $this->_isElementMultipleAllowed($xsdElements[$subKey]);

                            // frontend value is the value that will be presented in the data field
                            // If no metadata-file present, it will fall back to its default ONLY of in writable mode (i.e NO READER)
                            $frontendValue = array(0 => (isset($propertyElement['default']) AND $writeMode) ? $propertyElement['default'] : null);

                            // @todo - hoe zit dit precies met die $config
                            if ($config['hasMetadataXml'] == 'true' || $config['hasMetadataXml'] == 'yes') { // the value in the file supersedes default @todo!!!????????? hoe zit dit??
//                            $frontendValue = htmlspecialchars($keyValue, ENT_QUOTES, 'UTF-8');  // no purpose as it is superseded by next line
                                $frontendValue = $subKeyValue;
                            }

                            $multiPostFix = '';
                            if ($multipleAllowed) {
                                $multiPostFix = '[]'; // Create array as variable can have multiple values, hier mag [] - volgorde is niet belangrijk
                            }

                            $geoBoxCounter = 0;
                            foreach ($frontendValue as $fek => $fev) {

                                if ($this->_isGeoLocation($propertyElement)) {
                                 //   echo '<pre>';
                                 //       print_r($propertyElement);
                                 //   echo '</pre>';
                                    $elementKey = $key . $keyCounterInfix . '[' . $id . '][' . $propertyKey . ']';
                                    if ($multipleAllowed) {
                                        $elementKey .= '[' . $geoBoxCounter. ']';
                                    }
                                    $geoBoxCounter++;
                                }
                                else {
                                    $elementKey = $key . $keyCounterInfix . '[' . $id . '][' . $propertyKey . ']' . $multiPostFix;
                                }

                                $this->presentationElements[$groupName][] =
                                    $this->_addPresentationElement(
                                        $config, $xsdElements[$subKey], $propertyElement,
                                        $elementKey, //$key . $keyCounterInfix . '[' . $id . '][' . $propertyKey . ']' . $multiPostFix,
                                        $fev, $multipleAllowed, $subPropArray);
                            }
                        }
                    }
                }
            }

            $this->presentationElements[$groupName][] =
                $this->_addPresentationElement(
                    $config, array('type' => 'structSubPropertiesClose'), $em, $key . $keyCounterInfix . '[' . $id . ']', '', $multipleAllowed, $subPropArray);

            $elementCounterForFrontEnd++; // new element (if still present in array)
        }
        return true;
    }



// This fully adds an element that is a combination of all possible formelement-types
// Can also add these as a subproperty

// There are two keys
// one is directed at the frontend, to be used for an element's name.
// the other is used as an index at the internal arrays

// if counts are wrong in single element situation, return FALSE.
// Else return TRUE


    /**
     * @param $config
     * @param $groupName
     * @param $key
     * @param $element
     * @param $formData - enumerated highest level of data for this key
     * @param $xsdElements
     * @param string $elementOffsetFrontEnd
     * @param array $subPropArray
     * @return bool
     */
    private function _addCompoundElement($config, $groupName, $key, $element,
                                       $formData, $xsdElements, $elementOffsetFrontEnd = '', $subPropArray = array()) // presentation elements will be extended -> make it class variable
    {
        // A combined element from 2017/10/25 on has a title & help text
        if (isset($xsdElements[$key])) {

            // Extend the passed array with compound specific data for the frontend

            // $element holds the structure under the parent level
            // Count te be decremented by 1 as @attributes is not part of the fields for the frontend
            /**
            [@attributes] => Array
                [class] => compound

            [Persistent_Identifier]
                [label] => Persistent Identifier
                [help] => Persistent identifier PI (e.g. an ORCID, DAI, or ScopusID)

            [Persistent_Identifier_Type]
                [label] => Type of Persistent Identifier
                [help] => What type of persistent person identifier


             */
            $subPropArrayExtended = $subPropArray;

            $subPropArrayExtended['compoundFieldCount'] = $this->_getCountOfElementsInCompound($element);
            $subPropArrayExtended['compoundFieldPosition'] = 0;
            $subPropArrayExtended['compoundBackendArrayLevel'] = 0;

            if ($xsdElements[$key]['type'] == 'openTag') {

                if ($elementOffsetFrontEnd) {
                    unset($subPropArrayExtended['compoundBackendArrayLevel']);
                    $subPropArrayExtended['compoundBackendArrayLevel'] = 3;  // @todo - moet nog dynamisch!!!! maar nu klopt het altijd
                }

                if ($elementOffsetFrontEnd) { // is vanuit een subproperty siutatie
                    $baseCombiElementOffsetFrontEnd = $elementOffsetFrontEnd; // . "[$key]";
                } else {
                    $baseCombiElementOffsetFrontEnd = $key;
                }

                $combiElementMultipleAllowed = $this->_isElementMultipleAllowed($xsdElements[$key]);

                // front end should know whether entire compound is clonable in order to show the clone button
                $subPropArrayExtended['compoundMultipleAllowed'] = $combiElementMultipleAllowed;

                // Step though all values that are within yoda-metadata.xml
                $combiCounter = 0; // To create unique names in array form for frontend

                foreach ($formData as $arValues) { //   $formValues
                    // 1) Add start tag - based upon type = openTag
                    $combiElementName = $baseCombiElementOffsetFrontEnd;

//                    echo '<br>combination: ' . $combiElementName;
//
                    if ($combiElementMultipleAllowed) {
                        $combiElementName .= "[$combiCounter]";
                    }
//                    echo '<br>combination: ' . $combiElementName;

                    // This overall placeholder for multiple fields should indicate whether it is in its total mandatory
                    // 1) Mandatory for vault processing
                    // @todo:: Als er iets in het sublevel verplicht is, moet het top level ook verplicht aangeven????????????
                    // 2) MultipleAllowed [OK]
                    $this->presentationElements[$groupName][] =
                        $this->_addPresentationElement($config, array('type' => 'structCombinationOpen'), $element, $combiElementName, '', false, $subPropArrayExtended);

                    // 2) step through all elements to complete the combined field

                    $fieldPosition = 0;
                    foreach ($element as $id => $em) {
                        // $elements now holds an array of formelements - these are subproperties
                        $subKey = $key . '_' . $id;

                        if ($id != '@attributes') { // exclude this item added due to class=compound in the tag itself
                            // @todo: een element kan ook weer een multiple variabele zijn !!!!!!!!!!!!!!!!! nog meenemen later
                            $subCombiCounter = 0;
                            // => dus hier lopen if so en keyname for front end aanpassen
                            $subCombiMultipleAllowed = $this->_isElementMultipleAllowed($xsdElements[$subKey]);

                            unset($subPropArrayExtended['compoundFieldPosition']);
                            $subPropArrayExtended['compoundFieldPosition'] = $fieldPosition;

                            if ($this->_isGeoLocation($em)) {
                                $locationAttributeArray = array();

                                // Special case for geolocation - always should have keys westBoundLongitude, eastBoundLongitude, southBoundLatitude, northBoundLatitude
                                if (!is_numeric(key($arValues[$id]))) { // each index contains a one dimensional array with lat/longitude as key
                                    $locationAttributeArray[] = $arValues[$id]; //$formData[$key];
                                } else {
                                    $locationAttributeArray = $arValues[$id]; //$formData[$key];
                                }
                                //$multiPostFix = ''; // Addition to element name when mupliple instances can occur
                                if (!$subCombiMultipleAllowed) {
                                    if (count($locationAttributeArray) > 1) {
                                        return false; // break it off as this does not comply with xml
                                    }
                                } else {
                                    //    $multiPostFix = '[]'; // Create array as variable can have multiple values, hier mag [] - volgorde is niet belangrijk
                                }

                                $boxCounter = 0; // For correct processing of posted form data add identifier for location information PER BOX

                                foreach ($locationAttributeArray as $keyValue) {
                                    // each keyValue contains an array with keys: westBoundLongitude, eastBoundLongitude, southBoundLatitude, northBoundLatitude
                                    // And its actual value
                                    // The geoLocationBox view file will take care of handling each seperate box

                                    $geoKey = $combiElementName . '[' . $id . ']' . ($subCombiMultipleAllowed ? '[' . $boxCounter . ']' : '');
                                    $this->presentationElements[$groupName][] =
                                        $this->_addPresentationElement($config,
                                            $xsdElements[$subKey],
                                            $em,
                                            $geoKey,
                                            $keyValue,
                                            false,
                                            $subPropArrayExtended
                                            );

                                    $boxCounter++;

                                    $fieldPosition++; //klopt deze hier omdat er N elementen inputs worden toegevoegd HIDDEN
                                }
                            } else {
//                            $allSubCombiValues = array();
//                            $allSubCombiValues[] = $arValues[$subKey];
                                if (isset($xsdElements[$subKey])) {
                                    $this->presentationElements[$groupName][] =
                                        $this->_addPresentationElement($config,
                                            $xsdElements[$subKey],
                                            $em,
                                            $combiElementName . '[' . $id . ']' . ($subCombiMultipleAllowed ? '[]' : ''),
                                            $arValues[$id],
                                            false,
                                            $subPropArrayExtended);
                                    $subCombiCounter++;

                                    $fieldPosition++;
                                }
                            }

                        }
                    }
                    // 3) Add stop tag derived from the start tag
                    //$xsdEndTagElement = $xsdElements[$key];
                    //$xsdEndTagElement['type'] = 'endTag';
                    unset($subPropArrayExtended['fieldPosition']);
                    $this->presentationElements[$groupName][] =
                        $this->_addPresentationElement($config, array('type' => 'structCombinationClose'), $element, $combiElementName, '', false, $subPropArrayExtended);

                    $combiCounter++;
                }
            }
        }
        return true;
    }


    // incliuding start / stop tags
    //xsdELement => data from XSD
    // $element => data from formelements
    // keyId name of element in frontend

    private function _addPresentationElement($config, $xsdElement, $element, $keyId, $value, $overrideMultipleAllowed = false, $subpropertyInfo = array())
    {
        $writeMode = true;
        if ($config['userType'] == 'reader' || $config['userType'] == 'none') {
            $writeMode = false; // Distinnction made as readers, in case of no xml-file being present, should  NOT get default values
        }

        $frontendValue = (isset($element['default']) AND $writeMode) ? $element['default'] : null;

        if ($config['hasMetadataXml'] == 'true' || $config['hasMetadataXml'] == 'yes') {
            $frontendValue = $value;
        }

        if ($overrideMultipleAllowed) {
            $multipleAllowed = true;
        } else {
            $multipleAllowed = $this->_isElementMultipleAllowed($xsdElement);
        }
        // Mandatory no longer based on XSD but taken from formelements.xml
        $mandatory = false;
        if (isset($element['mandatory']) AND strtolower($element['mandatory']) == 'true') {
            $mandatory = true;
        }

        $elementOptions = array(); // holds the options
        $elementMaxLength = 0;
        // Determine restricitions/requirements for this
        switch ($xsdElement['type']) {
            case 'structCombinationOpen':
                $type = 'structCombinationOpen'; // Start combination of elements in 1 element so frontend knows that several passing items are brought to 1 element
                break;
            case 'structCombinationClose':
                $type = 'structCombinationClose'; // combination of elements in 1 element so frontend knows that several passing items are brought to 1 element is to an end
                break;
            case 'structSubPropertiesOpen':
                $type = 'structSubPropertiesOpen';
                break;
            case 'structSubPropertiesClose':
                $type = 'structSubPropertiesClose';
                break;
            case 'xs:date':
                $type = 'date';
                break;
            case 'stringURI':
            case 'stringNormal':
                $type = 'text';
                $elementMaxLength = $xsdElement['simpleTypeData']['maxLength'];
                break;
            case 'xs:integer':
                $type = 'numeric';
                $elementMaxLength = 10;  // arbitrary length for this moment
                break;
            case 'xs:anyURI':
                $type = 'text';
                $elementMaxLength = 1024;
                break;
            case 'stringLong':
                $type = 'textarea';
                $elementMaxLength = $xsdElement['simpleTypeData']['maxLength'];
                break;
            case 'KindOfDataTypeType': // different option types will be a 'select' element (these are yet to be determined)
                /*
                case 'optionsDatasetType':
                case 'optionsDatasetAccess':
                case 'optionsYesNo':
                case 'optionsOther':
                case 'optionsPersonalPersistentIdentifierType':
                */
            case (substr($xsdElement['type'], 0, 7) == 'options'):
                $elementOptions = $xsdElement['simpleTypeData']['options'];
                $type = 'select';
                break;
        }

        //'select' has options
        // 'edit/multiline' has length
        // 'date' has nothing extra
        // Handled separately as these specifics might grow.
        $elementSpecifics = array(); // holds all element specific info
        if ($type == 'text' OR $type == 'textarea' OR $type == 'numeric') {
            $elementSpecifics = array('maxLength' => $elementMaxLength);
        } elseif ($type == 'select') {
            $elementSpecifics = array('options' => $elementOptions);
        }

        // Special handling for value of flexDate - controls
        // Required as this is a value that is one level deeper due to flexible
        if (isset($element['@attributes']['flexdate'])) {
            $type = 'flexdate';
            $datePattern = 'YYYY-MM-DD';

            $flexDatePossibilities = array('YYYY_MM_DD' => 'YYYY-MM-DD',
                'YYYY' => 'YYYY',
                'YYYY_MM' => 'YYYY-MM');

            // Find the element name (highest present in tagNameRoutig
            $dateElementTagName = $xsdElement['tagNameRouting'][count($xsdElement['tagNameRouting']) - 1];

            foreach ($flexDatePossibilities as $dp => $pattern) {
                $dateTag = $dateElementTagName . '_' . $dp;

                if (isset($frontendValue[$dateTag])) { // if found, stop the search->there's only 1 hit
                    $frontendValue = $frontendValue[$dateTag];
                    $datePattern = $pattern;
                    break;
                }
            }

            $elementSpecifics = array('pattern' => $datePattern);
        }

        // Change type to geolocation selection box
        if (isset($element['@attributes']['geoLocation'])) {
            $type = 'geoLocationBox';
        }

        $elementData = array(
            'key' => $keyId, // Key to be used by frontend
            'value' => $frontendValue,
            'label' => $element['label'],
            'helpText' => $element['help'],
            'type' => $type,
            'mandatory' => $mandatory,
            'multipleAllowed' => $multipleAllowed,
            'elementSpecifics' => $elementSpecifics,
            'elementRouting' => $xsdElement['tagNameRouting']  // @todo: change tagNameRouting to elementRouting
        );

        if (count($subpropertyInfo)) {
            foreach ($subpropertyInfo as $key => $value) {
                $elementData[$key] = $value;
            }
        }
        return $elementData;
    }

    /**
     * @param $xsdElement
     * @return bool
     *
     * Supporting function for getFormELements
     * is multiple allowed for the given element from the xsd
     */
    private function _isElementMultipleAllowed($xsdElement)
    {
        $multipleAllowed = false;
        if ($xsdElement['maxOccurs'] != '1') {
            $multipleAllowed = true;
        }
        return $multipleAllowed;
    }

    /**
     * @param $value
     * @return array
     *
     * Supporting function for getFormELements
     *
     * return the value as being part of an array
     */
    private function _getElementValueAsArray($value)
    {
        // The number of values determine the number of elements to be created
        if (!is_array($value)) {
            $valueArray = array();
            $valueArray[] = $value;
        } else {
            $valueArray = $value;
            if (count($valueArray) == 0) {
                $valueArray = array('');
            }
        }
        return $valueArray;
    }


    private function _loadJSONS($rodsaccount, $xsdPath)
    {
        // with Related Datapackage - multiple (doesn't work)
        $jsonSchema = <<<'JSON'
{
	"definitions": {
		"stringURI": {
			"type": "uri",
			"maxLength": 1024
		},
		"stringNormal": {
			"type": "string",
			"maxLength": 255
		},
		"stringLong": {
			"type": "string",
			"maxLength": 2700
		},
		"optionsYesNo": {
			"type": "string",
			"enum": ["Yes", "No", "N.A."]
		},
		"optionsDataAccessRestriction": {
			"type": "string",
			"enum": [
				"Open - freely retrievable",
				"Restricted - available upon request",
				"Closed"
			]
		},
		"optionsDataClassification": {
			"type": "string",
			"enum": [
				"Public",
				"Basic",
				"Sensitive",
				"Critical"
			]
		},
		"optionsNameIdentifierScheme": {
			"type": "string",
			"enum": [
				"ORCID",
				"DAI",
				"Author identifier (Scopus)",
				"ResearcherID (Web of Science)",
				"ISNI"
			]
		},
		"optionsPersistentIdentifierScheme": {
			"type": "string",
			"enum": [
				"ARK",
				"arXiv",
				"bibcode",
				"DOI",
				"EAN13",
				"EISSN",
				"Handle",
				"ISBN",
				"ISSN",
				"ISTC",
				"LISSN",
				"LSID",
				"PMID",
				"PURL",
				"UPC",
				"URL",
				"URN"
			]
		},
		"optionsDiscipline": {
			"type": "string",
			"enum": [
				"Natural Sciences - Mathematics (1.1)",
				"Natural Sciences - Computer and information sciences (1.2)",
				"Natural Sciences - Physical sciences (1.3)",
				"Natural Sciences - Chemical sciences (1.4)",
				"Natural Sciences - Earth and related environmental sciences (1.5)",
				"Natural Sciences - Biological sciences (1.6)",
				"Natural Sciences - Other natural sciences (1.7)",
				"Engineering and Technology - Civil engineering (2.1)",
				"Engineering and Technology - Electrical engineering, electronic engineering, information engineering (2.2)",
				"Engineering and Technology - Mechanical engineering (2.3)",
				"Engineering and Technology - Chemical engineering (2.4)",
				"Engineering and Technology - Materials engineering (2.5)",
				"Engineering and Technology - Medical engineering (2.6)",
				"Engineering and Technology - Environmental engineering (2.7)",
				"Engineering and Technology - Environmental biotechnology (2.8)",
				"Engineering and Technology - Industrial Biotechnology (2.9)",
				"Engineering and Technology - Nano-technology (2.10)",
				"Engineering and Technology - Other engineering and technologies (2.11)",
				"Medical and Health Sciences - Basic medicine (3.1)",
				"Medical and Health Sciences - Clinical medicine (3.2)",
				"Medical and Health Sciences - Health sciences (3.3)",
				"Medical and Health Sciences - Health biotechnology (3.4)",
				"Medical and Health Sciences - Other medical sciences (3.5)",
				"Agricultural Sciences - Agriculture, forestry, and fisheries (4.1)",
				"Agricultural Sciences - Animal and dairy science (4.2)",
				"Agricultural Sciences - Veterinary science (4.3)",
				"Agricultural Sciences - Agricultural biotechnology (4.4)",
				"Agricultural Sciences - Other agricultural sciences (4.5)",
				"Social Sciences - Psychology (5.1)",
				"Social Sciences - Economics and business (5.2)",
				"Social Sciences - Educational sciences (5.3)",
				"Social Sciences - Sociology (5.4)",
				"Social Sciences - Law (5.5)",
				"Social Sciences - Political Science (5.6)",
				"Social Sciences - Social and economic geography (5.7)",
				"Social Sciences - Media and communications (5.8)",
				"Social Sciences - Other social sciences (5.9)",
				"Humanities - History and archaeology (6.1)",
				"Humanities - Languages and literature (6.2)",
				"Humanities - Philosophy, ethics and religion (6.3)",
				"Humanities - Art (arts, history of arts, performing arts, music) (6.4)",
				"Humanities - Other humanities (6.5)"
			]
		},
		"optionsContributorType": {
			"type": "string",
			"enum": [
				"ContactPerson",
				"DataCollector",
				"DataCurator",
				"DataManager",
				"Distributor",
				"Editor",
				"HostingInstitution",
				"Producer",
				"ProjectLeader",
				"ProjectManager",
				"ProjectMember",
				"RegistrationAgency",
				"RegistrationAuthority",
				"RelatedPerson",
				"Researcher",
				"ResearchGroup",
				"RightsHolder",
				"Sponsor",
				"Supervisor",
				"WorkPackageLeader"
			]
		},
		"optionsLicense": {
			"type": "string",
			"enum": [
				"Creative Commons Attribution 4.0 International Public License",
				"Creative Commons Attribution-ShareAlike 4.0 International Public License",
				"Open Data Commons Attribution License (ODC-By) v1.0",
				"Custom"
			]
		},
		"optionsRelationType": {
			"type": "string",
			"enum": [
				"IsSupplementTo: Current datapackage is supplement to",
				"IsSupplementedBy: Current datapackage is supplemented by",
				"IsContinuedBy: Current datadatapackage is continued by",
				"Continues: Continues this current dataset",
				"IsNewVersionOf: Current datapackage is new version of",
				"IsPreviousVersionOf: Current datapackage is previous version of",
				"IsPartOf: Current datapackage is part of",
				"HasPart: Is part of current datapackage",
				"IsReferencedBy: Current datapackage is referenced by",
				"References: Current datapackages references",
				"IsVariantFormOf: Current datapackage is variant of",
				"IsOriginalFormOf: Current datapackage is original of",
				"IsSourceOf: Raw data for this current datapackage"
			]
		},
		"optionsISO639-1": {
			"type": "string",
			"enum": [
				"ab - Abkhazian",
				"aa - Afar",
				"af - Afrikaans",
				"ak - Akan",
				"sq - Albanian",
				"am - Amharic",
				"ar - Arabic",
				"an - Aragonese",
				"hy - Armenian",
				"as - Assamese",
				"av - Avaric",
				"ae - Avestan",
				"ay - Aymara",
				"az - Azerbaijani",
				"bm - Bambara",
				"ba - Bashkir",
				"eu - Basque",
				"be - Belarusian",
				"bn - Bengali",
				"bh - Bihari languages",
				"bi - Bislama",
				"nb - Bokmål, Norwegian",
				"bs - Bosnian",
				"br - Breton",
				"bg - Bulgarian",
				"my - Burmese",
				"es - Castilian",
				"ca - Catalan",
				"km - Central Khmer",
				"ch - Chamorro",
				"ce - Chechen",
				"ny - Chewa",
				"ny - Chichewa",
				"zh - Chinese",
				"za - Chuang",
				"cu - Church Slavic",
				"cv - Chuvash",
				"kw - Cornish",
				"co - Corsican",
				"cr - Cree",
				"hr - Croatian",
				"cs - Czech",
				"da - Danish",
				"dv - Dhivehi",
				"dv - Divehi",
				"nl - Dutch",
				"dz - Dzongkha",
				"en - English",
				"eo - Esperanto",
				"et - Estonian",
				"ee - Ewe",
				"fo - Faroese",
				"fj - Fijian",
				"fi - Finnish",
				"nl - Flemish",
				"fr - French",
				"ff - Fulah",
				"gd - Gaelic",
				"gl - Galician",
				"lg - Ganda",
				"ka - Georgian",
				"de - German",
				"ki - Gikuyu",
				"el - Greek, Modern (1453-)",
				"kl - Greenlandic",
				"gn - Guarani",
				"gu - Gujarati",
				"ht - Haitian",
				"ht - Haitian Creole",
				"ha - Hausa",
				"he - Hebrew",
				"hz - Herero",
				"hi - Hindi",
				"ho - Hiri Motu",
				"hu - Hungarian",
				"is - Icelandic",
				"io - Ido",
				"ig - Igbo",
				"id - Indonesian",
				"ia - Interlingua (International Auxiliary Language Association)",
				"ie - Interlingue",
				"iu - Inuktitut",
				"ik - Inupiaq",
				"ga - Irish",
				"it - Italian",
				"ja - Japanese",
				"jv - Javanese",
				"kl - Kalaallisut",
				"kn - Kannada",
				"kr - Kanuri",
				"ks - Kashmiri",
				"kk - Kazakh",
				"ki - Kikuyu",
				"rw - Kinyarwanda",
				"ky - Kirghiz",
				"kv - Komi",
				"kg - Kongo",
				"ko - Korean",
				"kj - Kuanyama",
				"ku - Kurdish",
				"kj - Kwanyama",
				"ky - Kyrgyz",
				"lo - Lao",
				"la - Latin",
				"lv - Latvian",
				"lb - Letzeburgesch",
				"li - Limburgan",
				"li - Limburger",
				"li - Limburgish",
				"ln - Lingala",
				"lt - Lithuanian",
				"lu - Luba-Katanga",
				"lb - Luxembourgish",
				"mk - Macedonian",
				"mg - Malagasy",
				"ms - Malay",
				"ml - Malayalam",
				"dv - Maldivian",
				"mt - Maltese",
				"gv - Manx",
				"mi - Maori",
				"mr - Marathi",
				"mh - Marshallese",
				"ro - Moldavian",
				"ro - Moldovan",
				"mn - Mongolian",
				"na - Nauru",
				"nv - Navaho",
				"nv - Navajo",
				"nd - Ndebele, North",
				"nr - Ndebele, South",
				"ng - Ndonga",
				"ne - Nepali",
				"nd - North Ndebele",
				"se - Northern Sami",
				"no - Norwegian",
				"nb - Norwegian Bokmål",
				"nn - Norwegian Nynorsk",
				"ii - Nuosu",
				"ny - Nyanja",
				"nn - Nynorsk, Norwegian",
				"ie - Occidental",
				"oc - Occitan (post 1500)",
				"oj - Ojibwa",
				"or - Oriya",
				"om - Oromo",
				"os - Ossetian",
				"os - Ossetic",
				"pi - Pali",
				"pa - Panjabi",
				"ps - Pashto",
				"fa - Persian",
				"pl - Polish",
				"pt - Portuguese",
				"pa - Punjabi",
				"ps - Pushto",
				"qu - Quechua",
				"ro - Romanian",
				"rm - Romansh",
				"rn - Rundi",
				"ru - Russian",
				"sm - Samoan",
				"sg - Sango",
				"sa - Sanskrit",
				"sc - Sardinian",
				"gd - Scottish Gaelic",
				"sr - Serbian",
				"sn - Shona",
				"ii - Sichuan Yi",
				"sd - Sindhi",
				"si - Sinhala",
				"si - Sinhalese",
				"sk - Slovak",
				"sl - Slovenian",
				"so - Somali",
				"st - Sotho, Southern",
				"nr - South Ndebele",
				"es - Spanish",
				"su - Sundanese",
				"sw - Swahili",
				"ss - Swati",
				"sv - Swedish",
				"tl - Tagalog",
				"ty - Tahitian",
				"tg - Tajik",
				"ta - Tamil",
				"tt - Tatar",
				"te - Telugu",
				"th - Thai",
				"bo - Tibetan",
				"ti - Tigrinya",
				"to - Tonga (Tonga Islands)",
				"ts - Tsonga",
				"tn - Tswana",
				"tr - Turkish",
				"tk - Turkmen",
				"tw - Twi",
				"ug - Uighur",
				"uk - Ukrainian",
				"ur - Urdu",
				"ug - Uyghur",
				"uz - Uzbek",
				"ca - Valencian",
				"ve - Venda",
				"vi - Vietnamese",
				"vo - Volapük",
				"wa - Walloon",
				"cy - Welsh",
				"fy - Western Frisian",
				"wo - Wolof",
				"xh - Xhosa",
				"yi - Yiddish",
				"yo - Yoruba",
				"za - Zhuang",
				"zu - Zulu"
			]
		}
	},

	"title": "",
	"type": "object",
	"properties": {
		"Descriptive-group": {
			"type": "object",
			"title": "Descriptive",
			"required": [
				"Title",
				"Description",
				"Version"
			],
			"properties": {
				"Title": {
					"$ref": "#/definitions/stringNormal",
					"title": "Title"
				},
				"Description": {
					"$ref": "#/definitions/stringLong",
					"title": "Description"
				},
				"Discipline": {
					"type": "array",
					"items": {
						"$ref": "#/definitions/optionsDiscipline",
						"title": "Discipline"
					}
				},
				"Version": {
					"$ref": "#/definitions/stringNormal",
					"title": "Version"
				},
				"Language": {
					"$ref": "#/definitions/optionsISO639-1",
					"title": "Language of the data"
				},
				"Collected": {
					"type": "object",
					"title": "Collection process",
					"yoda:structure": "compound",
					"properties": {
						"Start_Date": {
							"type": "string",
							"format": "date",
							"title": "Start date"
						},
						"End_Date": {
							"type": "string",
							"format": "date",
							"title": "End date"
						}
					}
				},
				"Covered_Geolocation_Place": {
					"type": "array",
					"items": {
						"$ref": "#/definitions/stringNormal",
						"title": "Location(s) covered"
					}
				},
				"Covered_Period": {
					"type": "object",
					"title": "Period covered",
					"yoda:structure": "compound",
					"properties": {
						"Start_Date": {
							"type": "string",
							"format": "date",
							"title": "Start date"
						},
						"End_Date": {
							"type": "string",
							"format": "date",
							"title": "End date"
						}
					}
				},
				"Tag": {
					"type": "array",
					"items": {
						"$ref": "#/definitions/stringNormal",
						"title": "Tag"
					}
				},
				"Related_Datapackage": {
					"type": "array",
					"minItems": 0,
					"items": {
						"type": "object",
						"yoda:structure": "subproperties",
						"required": [
							"Title"
						],
						"properties": {
							"Relation_Type": {
								"$ref": "#/definitions/optionsRelationType",
								"title": "Related Datapackage"
							},
							"Title": {
								"$ref": "#/definitions/stringNormal",
								"title": "Title"
							},
							"Persistent_Identifier": {
								"type": "object",
								"title": "Persistent Identifier",
								"yoda:structure": "compound",
								"properties": {
									"Identifier_Scheme": {
										"$ref": "#/definitions/optionsPersistentIdentifierScheme",
										"title": "Type"
									},
									"Identifier": {
										"$ref": "#/definitions/stringNormal",
										"title": "Identifier"
									}
								}
							}
						}
					}
				}
			}
		},

		"Administrative-group": {
			"type": "object",
			"title": "Administrative",
			"required": [
				"Retention_Period",
				"Data_Classification"
			],
			"properties": {
				"Retention_Period": {
					"type": "integer",
					"title": "Retention period (years)"
				},
				"Retention_Information": {
					"$ref": "#/definitions/stringNormal",
					"title": "Retention information"
				},
				"Embargo_End_Date": {
					"type": "string",
					"format": "date",
					"title": "Embargo end date"
				},
				"Data_Classification": {
					"$ref": "#/definitions/optionsDataClassification",
					"title": "Data classification"
				},
				"Collection_Name": {
					"$ref": "#/definitions/stringNormal",
					"title": "Name of collection"
				},
				"Funding_Reference": {
					"type": "array",
					"items": {
						"type": "object",
						"title": "Funder",
						"yoda:structure": "subproperties",
						"properties": {
							"Funder_Name": {
								"$ref": "#/definitions/stringNormal",
								"title": "Funder"
							},
							"Award_Number": {
								"$ref": "#/definitions/stringNormal",
								"title": "Award number"
							}
						}
					}
				}
			}
		},

		"Rights-group": {
			"type": "object",
			"title": "Rights",
			"required": [
				"License",
				"Data_Access_Restriction"
			],
			"properties": {
				"Creator": {
					"type": "array",
					"minItems": 0,
					"items": {
						"type": "object",
						"yoda:structure": "subproperties",
						"required": [
							"Name",
							"Affiliation"
						],
						"properties": {
							"Name": {
								"$ref": "#/definitions/stringNormal",
								"title": "Creator of datapackage"
							},
							"Affiliation": {
								"type": "array",
								"items": {
									"$ref": "#/definitions/stringNormal",
									"title": "Affiliation"
								}
							},
							"Person_Identifier": {
								"type": "array",
								"items": {
									"type": "object",
									"title": "Person Identifier",
									"yoda:structure": "compound",
									"properties": {
										"Name_Identifier_Scheme": {
											"$ref": "#/definitions/optionsNameIdentifierScheme",
											"title": "Type"
										},
										"Name_Identifier": {
											"$ref": "#/definitions/stringNormal",
											"title": "Identifier"
										}
									}
								}
							}
						}
					}
				},
				"Contributor": {
					"type": "array",
					"items": {
						"type": "object",
						"yoda:structure": "subproperties",
						"required": [
							"Contributor_Type",
							"Affiliation"
						],
						"properties": {
							"Name": {
								"$ref": "#/definitions/stringNormal",
								"title": "Contributor of datapackage"
							},
							"Contributor_Type": {
								"$ref": "#/definitions/optionsContributorType",
								"title": "Contributor type"
							},
							"Affiliation": {
								"type": "array",
								"items": {
									"$ref": "#/definitions/stringNormal",
									"title": "Affiliation"
								}
							},
							"Person_Identifier": {
								"type": "array",
								"items": {
									"type": "object",
									"title": "Person Identifier",
									"yoda:structure": "compound",
									"properties": {
										"Name_Identifier_Scheme": {
											"$ref": "#/definitions/optionsNameIdentifierScheme",
											"title": "Type"
										},
										"Name_Identifier": {
											"$ref": "#/definitions/stringNormal",
											"title": "Identifier"
										}
									}
								}
							}
						}
					}
				},
				"License": {
					"$ref": "#/definitions/optionsLicense",
					"title": "License"
				},
				"Data_Access_Restriction": {
					"$ref": "#/definitions/optionsDataAccessRestriction",
					"title": "Data package access"
				}
			}
		}
	}
}
JSON;

        return json_decode($jsonSchema, true);
    }


    /**
     * @param $rodsaccount
     * @param $path
     * @return array|bool
     *
     * Load XSD schema and reorganize in such a way that it coincides with formelements
     *
     * XSD can hold hierarchical complex types now that must match formelements definitions
     */
    public function loadXsd($rodsaccount, $path)
    {
        $fileContent = $this->CI->filesystem->read($rodsaccount, $path);

        $xml = simplexml_load_string($fileContent, "SimpleXMLElement", 0, 'xs', true);

        if (empty($xml)) {
            return false;
        }

        // At first simpleType handling - gathering limitations/restrictions/requirements
        $simpleTypeData = array();

//        echo '<pre>';
//        print_r($xml);
//        echo '</pre>';
//        echo '<hr>';

        foreach ($xml->simpleType as $key => $stype) {
            // simpleTye names
            $simpleTypeAttributes = (array)$stype->attributes();

            $simpleTypeName = $simpleTypeAttributes['@attributes']['name'];

            $restriction = (array)$stype->restriction;

            // typical handling here
            if (isset($restriction['maxLength'])) {
                $lengthArray = (array)$stype->restriction->maxLength->attributes();
                $length = $lengthArray['@attributes']['value'];
                $simpleTypeData[$simpleTypeName]['maxLength'] = $length;
            }
            if (isset($restriction['enumeration'])) {
                $options = array();
                foreach ($stype->restriction->enumeration as $enum) {
                    $optionsArray = (array)$enum->attributes();
                    $options[] = $optionsArray['@attributes']['value'];
                }
                $simpleTypeData[$simpleTypeName]['options'] = $options;
            }
        }

        $supportedSimpleTypes = array_keys($simpleTypeData);
        $supportedSimpleTypes[] = 'xs:date'; // add some standard xsd simpleTypes that should be working as well
        $supportedSimpleTypes[] = 'xs:gYear';
        $supportedSimpleTypes[] = 'xs:gYearMonth';
        $supportedSimpleTypes[] = 'xs:anyURI';
        $supportedSimpleTypes[] = 'xs:integer';
        // Basic information is complete

        // NOW collect stuff regarding fields

        $xsdElements = array();

        // Hier moet eigenlijk op getest worden of dit info bevat. Dit is de kern van informatie die je bij alle zaken brengt.
        // DIt is zelfs hierarchisch dus zou
        $elements = $xml->element->complexType->sequence->element;
        // @todo: maybe add choice as possible complexType. This like under _addXsdElements()

        $this->_addXsdElements($xsdElements, $elements, $supportedSimpleTypes, $simpleTypeData);

//        echo '<pre>xsdElements: <br>';
//        print_r($xsdElements);
//        echo '</pre>';
//        exit;

        return $xsdElements;
    }

    /**
     * @param $xsdElements
     * @param $elements
     * @param $supportedSimpleTypes
     * @param $simpleTypeData
     * @param string $prefixHigherLevel

    Handling of hierarchical elements
     */
    private function _addXsdElements(&$xsdElements, $elements, $supportedSimpleTypes, $simpleTypeData, $prefixHigherLevel = '')
    {
        foreach ($elements as $element) {
            $attributes = $element->attributes();

            $elementName = '';
            $elementType = '';
            $minOccurs = 0;
            $maxOccurs = 1;

            foreach ($attributes as $attribute => $simpleXMLvalue) {
                $arrayValue = (array)$simpleXMLvalue;
                $value = $arrayValue[0];
                switch ($attribute) {
                    case 'name':
                        $elementName = $value;
                        break;
                    case 'type':
                        $elementType = $value;
                        break;
                    case 'minOccurs':
                        $minOccurs = $value;
                        break;
                    case 'maxOccurs':
                        $maxOccurs = $value;
                        break;
                }
            }

//            echo '<br> PrefixHigherLevel: ' . $prefixHigherLevel;
//            echo '<br> Element name: '.$elementName.'<br>';

            $sequenceType = 'sequence';
            $isDeeperLevel = false;

            $sequenceTypes = array('sequence', 'choice');
            foreach ($sequenceTypes as $seqType) {
                if (@property_exists($element->complexType->$seqType, 'element')) {  // Starting tag Deeper level
                    $isDeeperLevel = true;
                    $sequenceType = $seqType;
                    break;
                }
            }

            // with parentElement the routing to this element can be set
            // Routing is required to address directly in formData-array or formElements-array when processing
            $parentElementName = substr($prefixHigherLevel, 0, strlen($prefixHigherLevel) - 1);

            $routing = array();
            if (isset($xsdElements[$parentElementName]['tagNameRouting'])) {
                $routing = $xsdElements[$parentElementName]['tagNameRouting'];
                $routing[] = $elementName;
            } else {
                $routing[] = $elementName;
            }
            // each relevant attribute has been processed.
            if (in_array($elementType, $supportedSimpleTypes) OR $isDeeperLevel) {
                $xsdElements[$prefixHigherLevel . $elementName] = array(
                    'type' => ($isDeeperLevel ? 'openTag' : $elementType),
                    'minOccurs' => $minOccurs,
                    'maxOccurs' => $maxOccurs,
                    'simpleTypeData' => isset($simpleTypeData[$elementType]) ? $simpleTypeData[$elementType] : array(),
                    'tagNameRouting' => $routing
                );
            }

            if ($isDeeperLevel) { // Add new level and extend the prefix-identifier ->sequence type is determined above (can be 'sequence' or 'choice')
                $elementSubLevel = $element->complexType->$sequenceType->element;

                //$prefixHigherLevel = $elementName . '_'; // to be used to identify elements

                $prefixHigherLevelKeep = $prefixHigherLevel;
                if (!$prefixHigherLevel) {
                    $prefixHigherLevel = $elementName . '_';
                } else {
                    $prefixHigherLevel = $prefixHigherLevel . $elementName . '_';
                }


                // dieper niveau uitvoeren op basis an de gestelde prefix.
                $this->_addXsdElements($xsdElements, $elementSubLevel, $supportedSimpleTypes, $simpleTypeData, $prefixHigherLevel);

                $prefixHigherLevel = $prefixHigherLevelKeep; //''; //reset it again

                // Closing tag - Deeper level
                $xsdElements[$prefixHigherLevel . $elementName . '_close'] = array(
                    'type' => 'closeTag',
                    'minOccurs' => $minOccurs,
                    'maxOccurs' => $maxOccurs,
                    'simpleTypeData' => isset($simpleTypeData[$elementType]) ? $simpleTypeData[$elementType] : array()
                );
            }
        }
    }

    /**
     * @param $rodsaccount
     * @param $path
     * @return array|bool
     *
     * Load the yoda-metadata.xml file ($path) in an array structure
     *
     * Reorganise this this in such a way that hierarchy is lost but indexing is possible by eg 'Author_Property_Role'
     */

    /** USER IN NEW SITUATION */
    public function loadFormData($rodsaccount, $path)
    {
        $fileContent = $this->CI->filesystem->read($rodsaccount, $path);
        libxml_use_internal_errors(true);
        $xmlData = simplexml_load_string($fileContent);
        $errors = libxml_get_errors();
        libxml_clear_errors();

        if (count($errors)) {
            return false;
        }

        $json = json_encode($xmlData);

        $formData = json_decode($json, TRUE);

        return $formData;
    }

    /**
     * @param $rodsaccount
     * @param $path
     * @return bool|mixed
     *
     * Load the required xml file with formelements description and return as an array
     */
    public function loadFormElements($rodsaccount, $path)
    {
        $fileContent = $this->CI->filesystem->read($rodsaccount, $path);

        if (empty($fileContent)) {
            return false;
        }
        $xmlFormElements = simplexml_load_string($fileContent);

        $json = json_encode($xmlFormElements);

        $data = json_decode($json, TRUE);

        // Single presence of a group requires extra handling
        if (!isset($data['Group'][0])) {
            $keepGroupData = $data['Group'];
            unset($data['Group']);
            $data['Group'][0] = $keepGroupData;
        }

        if ($data) {  // make the descriptive data for the actual form fields (i.e. not grouping info) accessible globally in class
            $this->formMainFields  = $this->_getFormElementsMainFieldList($data);
        }

        return $data;
    }

    private function _getFormElementsMainFieldList($formGroupedElements)
    {
        $elements = array();

        $formAllGroupedElements = array();
        foreach ($formGroupedElements['Group'] as $index => $array) {
            if ($index == '0') {
                // is the index of an array. So we have multiple groups.
                $formAllGroupedElements = $formGroupedElements['Group'];
            } else {
                $formAllGroupedElements[] = $formGroupedElements['Group']; // rewrite it as an indexable array as input for coming foreach
            }
            break;
        }

        // Form elements is hierarchical too.
        // Bring it back to one level with
        // 1) parent indication
        // 2) fully qualified name (unique!)
        // 3) Add start end tags for combination fields

        // HIER ALLEEN DE HOOFD ELEMENTEN
        foreach ($formAllGroupedElements as $formElements) {
            foreach ($formElements as $key => $element) {
                // Find group definition
                // Find hierarchies of elements regarding subproperties.
                if ($key != '@attributes') { // GROUP handling
//                    echo '<pre>';
//                    echo 'Key = ' . $key;
//                    echo '<br>';
//                    print_r($element);
//                    echo '</pre>';

                    $elements[$key] = $element;
                }
            }
        }
        return $elements;
    }
}