<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class MetadataVerification {
	protected $CI;

	public function __construct() {
		$this->CI =& get_instance();
	}

	/**
	 * Function that checks the dependency properties for a field
	 * @param fieldDependencies 	The dependency object in the field
	 * 								definitions of the field that is to
	 * 								be checked
	 * @param formData 				The posted form data
	 * @return bool 				True iff dependency indicates field
	 * 									was visible
	 */
	public function evaluateRowDependencies($fieldDependencies, $formData){
		if($fieldDependencies == null || $fieldDependencies == false)
			return true;

		$truthVals = array();
		$fields = $fieldDependencies["fields"];
		if(count($fields) > 0 && !array_key_exists(0, $fields) || is_array($fields[0])) {
			$fields = array($fields);
		}

		foreach($fields as $field) {
			array_push($truthVals, $this->evaluateSingleFieldDependency($field, $formData));
		}

		$condition = $this->checkCondition($fieldDependencies["if"], $truthVals);

		return !($condition === false || $fieldDependencies["action"] === "hide");
	}

	/**
	 * Function that checks the values for a key to see if all values
	 * satisfy all constraints
	 * @param $values 		The value, or value list, of the 
	 * 						field as submitted by the user
	 * @param $definition 	The field definition as defined in the 
	 * 						meta data schema
	 * @param $final (opt) 	True if the final check should be 
	 * 						performed (more strict)
	 * @return bool 		True iff the values satisfie all 
	 * 						constraints
	 */
	public function verifyKey($values, $definition, $final = false) {

		if(keyIsTrue($definition, "multiple") || $definition["type"] == "checkbox") {
			$errors = array();

			$mult = 
				keyIsTrue($definition, "multiple") ? 
				$definition["multiple"] : 
				(
					keyIsTrue($definition, "type_configuration") ?
					$definition["type_configuration"] : array()
				);

			if($final && keyIsTrue($mult, "min") && $mult["min"] > 0 && $mult["min"] > sizeof($values)){
				$errors[] = ERROR_MIN_ENTRIES;
			}

			if(
				!keyIsTrue($mult, "infinite") && 
				keyIsTrue($mult, "max") && $mult["max"] < sizeof($values)) {
				$errors[] = ERROR_MAX_ENTRIES;
			}

			foreach($values as $value) {
				$errors = array_merge($errors, $this->verifyField($value, $definition, $final));
			}

			return $errors;

		} else {
			if(gettype($values) == gettype(array()) && sizeof($values) > 1) {
				return array(ERROR_SINGLE_ENTRY);
			}
			return $this->verifyField($values, $definition, $final);
		}
	}


	/**
	 * Function that checks if a single dependency for a field is met
	 * @param fieldRequirements 	The requirements for the single field
	 * @param formData 	 			The posted form data
	 * @return Bool 				True iff the single requirement is met
	 */
	private function evaluateSingleFieldDependency($fieldRequirements, $formData){
		if($fieldRequirements == null) return true;

		$value = (array_key_exists($fieldRequirements["field_name"], $formData)) ?
			$formData[$fieldRequirements["field_name"]] : "";

		if(keyIsTrue($fieldRequirements, "operator")) {
			if(keyIsTrue($fieldRequirements, array("value", "fixed")))  {
				return $this->checkOperator(
					$fieldRequirements["operator"], 
					$value, 
					$fieldRequirements["value"]["fixed"]
				);
			} else if(keyIsTrue($fieldRequirements, array("value", "like"))) {
				return $this->checkLikeOperator(
					$fieldRequirements["operator"],
					$value,
					$fieldRequirements["value"]["like"]
				);
			} else if(keyIsTrue($fieldRequirements, array("value", "regex"))) {
				return $this->checkRegexOperator(
					$fieldRequirements["operator"],
					$value,
					$fieldRequirements["value"]["regex"]
				);
			}
		}

		return true;
	}

	private function checkLikeOperator($operator, $a, $b) {
		return call_user_func(array($this, $this->likeOperators[$operator]), $a, $b);
	}

	private function checkRegexOperator($operator, $a, $b) {
		if($b[0] != "/") $b = "/" . $b;
		if(substr($b, -1) != "/") $b .= "/";
		return call_user_func(array($this, $this->regexOperators[$operator]), $a, $b);
	}

	private function checkOperator($operator, $a, $b) {
		return call_user_func(array($this, $this->operators[$operator]), $a, $b);
	}

	private function checkCondition($condition, $arr) {
		return call_user_func(array($this, $condition), $arr);
	}

	private function equals($a, $b) { return $a == $b; }
	private function does_not_equal($a, $b) {return $a != $b; }
	private function is_larger_than($a, $b) {return $a > $b; }
	private function is_larger_than_or_equal($a, $b) {return $a >= $b;}
	private function is_less_than($a, $b) {return $a < $b; }
	private function is_less_than_or_equal($a, $b) {return $a <= $b; }
	private function all($arr) {return count(array_unique($arr)) === 1 && current($arr) === true;}
	private function none($arr) {return count(array_unique($arr)) === 1 && current($arr) === false;}
	private function any($arr) {return in_array(true, $arr); }
	private function is_like($a, $b) {return strpos(strtolower($a), strtolower($b)) !== false; }
	private function is_not_like($a, $b) {return strpos(strtolower($a), strtolower($b)) === false; }
	private function matches_regex($a, $b) {return preg_match($b, $a) > 0; }
	private function not_matches_regex($a, $b) {return preg_match($b, $a) === 0; }

	private $likeOperators = array(
		"==" => "is_like",
		"!=" => "is_not_like"
	);

	private $regexOperators = array(
		"==" => "matches_regex",
		"!=" => "not_matches_regex"
	);

	private $operators = array(
		"==" => "equals",
		"!=" => "does_not_equal",
		">" => "is_larger_than",
		">=" => "is_larger_than_or_equal",
		"<" => "is_less_than",
		"<=" => "is_less_than_or_equal",
		"gt" => "is_larger_than",
		"geq" => "is_larger_than_or_equal",
		"lt" => "is_less_than",
		"leq" => "is_less_than_or_equal"
	);

	/** 
	 * Function that checks if a field satisfies all constraints
	 * defined in the meta data schema.
	 * @param $value 		The value, or value list, of the 
	 * 						field as submitted by the user
	 * @param $definition 	The field definition as defined in the 
	 * 						meta data schema
	 * @param $formdata 	The posted formdata for reference
	 * @param $final (opt) 	True if the final check should be 
	 * 						performed (more strict)
	 * @return bool 		True iff the value satisfies all 
	 * 						constraints
	 */
	private function verifyField($value, $definition, $formdata, $final = false) {
		$errors = array();

		if($final && keyIsTrue($definition, "required") && $definition["required"] === True && !isset($value)) {
			$errors[] = ERROR_REQUIRED;
		}

		// Check maximum length
		if(
			keyIsTrue($definition, array("type_configuration", "length")) && 
			sizeof($value) > $definition["type_configuration"]["length"]
		) {
			$errors[] = ERROR_MAX_LENGTH;
		}

		if(keyIsTrue($definition, array("type_configuration", "pattern")) && $definition["type_configuration"]["pattern"] !== "*") {
			$patt = $definition["type_configuration"]["pattern"];

			if($patt[0] != "/") $patt = "/" . $patt;
			if(substr($patt, -1) != "/") $patt .= "/";

			if(preg_match($patt, $value) === 0 && !(!$final && (
					(is_array($value) && sizeof($value) === 0) ||
					(is_string($value) && strlen($value) === 0)
				))) {

				$errors[] = ERROR_REGEX;
			}
		}

		if(keyIsTrue($definition, array("type_configuration", "restricted"))) {
			if(!keyIsTrue($definition, array("type_configuration", "allow_create"))) {
				// TODO: check if the value exists for the key
				// Q: This requires another connection to iRODS for each field that uses restricted
				// values. Is this worth it?
			}
		}

		if(keyIsTrue($definition, array("type_configuration", "begin"))) {
			if(
				$value !== "" &&
				keyIsTrue($definition, array("type_configuration", "step")) && 
				intval($definition["type_configuration"]["step"]) < 0 &&
				intval($definition["type_configuration"]["begin"]) < intval($value)
				
			) {
				$errors[] = ERROR_NOT_IN_RANGE;
			}
			else if(
				(
					!keyIsTrue($definition, array("type_configuration", "step")) || 
					intval($definition["type_configuration"]["step"]) > 0
				) && $value !== "" && $definition["type_configuration"]["begin"] > $value
			) {
				$errors[] = ERROR_NOT_IN_RANGE;
			}
		}

		if(keyIsTrue($definition, array("type_configuration", "end"))) { // array_key_exists("end", $conf)) {
			if(
				$value !== "" &&
				keyIsTrue($definition, array("type_configuration", "step")) &&
				intval($definition["type_configuration"]["step"]) < 0 &&
				intval($definition["type_configuration"]["end"]) > intval($value) 
			) {
				$errors[] = ERROR_NOT_IN_RANGE;
			}
			else if(
				(
					!keyIsTrue($definition, array("type_configuration","step")) ||
					intval($definition["type_configuration"]["step"]) > 0
				) &&
				$value != "" && 
				intval($definition["type_configuration"]["end"]) < intval($value)
			) {
				$errors[] = ERROR_NOT_IN_RANGE;
			}
		}

		if($definition["type"] == "datetime") {
			$err = $this->verifyDateTime($value, $definition, $formdata, $final);
			if($err != OK) {
				$errors[] = $err;
			}
		}

		if(keyIsTrue($definition, array("type_configuration","options"))) {
			$options = array();
			foreach($definition["type_configuration"]["options"] as $key => $option) {
				if(is_string($option)) {
					$options[] = $option;
				} else if(is_array($option)) {
					foreach($option as $optgroup) {
						if(array_key_exists("option", $optgroup) && is_array($optgroup["option"])) {
							$options = array_merge($options, $optgroup["option"]);
						} else if(array_key_exists("option", $optgroup) && is_string($optgroup["option"])) {
							$options[] = $optgroup["option"];
						}
					}
				}
			}

			if(!in_array($value, $options) && $value != "") {
				$errors[] = ERROR_NOT_IN_RANGE;
			}
		}

		return $errors;
	}

	function callback_isNotEmpty($var) {
		return $var !== null && !empty($var) && $var !== "" && !(is_array($var) && sizeof($var) === 0);
	}

	/**
	 * Method that verifies a date time input by building a regex based
	 * on the field definitions
	 * @param value 		The user input value for this field
	 * @param definition 	The field definition for the field
	 * @param formdata 		All formdata submitted by the user
	 * @param final 		Boolean set to true if this should be the final check
	 */
	private function verifyDateTime($value, $definition, $formdata, $final = false){

		if(!$this->callback_isNotEmpty($value)) {
			return OK;
		}

		$needsDashReg = "/.+[^:\/ -]$/";
		$regex = "/^";
		$format = "";
		if(keyIsTrue($definition, array("type_configuration", "show_years"))) {
			$regex .= "\d{4}";
			$format .= "YYYY";
		}
		if(keyIsTrue($definition, array("type_configuration", "show_months"))) {
			if(preg_match($needsDashReg, $regex)){
				$regex .= "-";
				$format .= "-";
			}
			$regex .= "(?:(?:0[1-9])|(?:1(?:0|1|2)))";
			$format .= "MM";
		}
		if(keyIsTrue($definition, array("type_configuration", "show_days"))) {
			if(preg_match($needsDashReg, $regex)){
				$regex .= "-";
				$format .= "-";
			}
			$regex .= "(?:(?:0[1-9])|(?:[1-2][0-9])|(?:3[0-1]))";
			$format .= "DD";
		}
		if(keyIsTrue($definition, array("type_configuration", "show_time"))) {
			if(preg_match($needsDashReg, $regex)){
				$regex .= " ";
				$format .= "  ";
			}
			$regex .= "(?:[0-1][0-9]|2[0-3]):[0-5][0-9]";
			$format .= "HH:ii";
		}
		$regex .= "$/";

		if(!preg_match($regex, $value)){
			return ERROR_INVALID_DATETIME_FORMAT;
		}

		if(keyIsTrue($definition, array("type_configuration", "min_date_time"))) {
			if(keyIsTrue($definition, array("type_configuration", "min_date_time", "fixed"))) {
				try{
					$valDate = DateTime::createFromFormat($format, $value);
					$referenceDate = DateTime::createFromFormat($format, $definition["type_configuration"]["min_date_time"]["fixed"]);
					if($valDate < $referenceDate) {
						return ERROR_DATE_LESS_THAN_FIXED;
					}
				} catch(exception $e) {
					// do nothing
				}
			} else if(keyIsTrue($definition, array("type_configuration", "min_date_time", "linked"))) {
				if(array_key_exists($definition["type_configuration"]["min_date_time"]["linked"], $formdata)) {
					try{
						$valDate = DateTime::createFromFormat($format, $value);

						$refValArr = is_array($formdata[$definition["type_configuration"]["min_date_time"]["linked"]]) ? $formdata[$definition["type_configuration"]["min_date_time"]["linked"]] : array($formdata[$definition["type_configuration"]["min_date_time"]["linked"]]);
						$refVal = array_filter(
							$refValArr, 
							array($this, "callback_isNotEmpty")
						);
						if(is_array($refVal)) {
							if(!$this->callback_isNotEmpty($refVal)) $refVal = array("");
							$refVal = min($refVal);
						}
						$referenceDate = DateTime::createFromFormat($format, $refVal);
						if($refVal == "" && $final) {
							return ERROR_REQUIRED;
						} else if($refVal != "" && $valDate < $referenceDate) {
							return ERROR_DATE_LESS_THAN_LINKED;
						}
					} catch(Exception $e) {
						// do nothing
					}
				}
			}
		}

		if(keyIsTrue($definition, array("type_configuration", "max_date_time"))) {
			if(keyIsTrue($definition, array("type_configuration", "max_date_time", "fixed"))) {
				try{
					$valDate = DateTime::createFromFormat($format, $value);
					$referenceDate = DateTime::createFromFormat($format, $defintion["type_configuration"]["max_date_time"]["fixed"]);
					if($valDate > $referenceDate) {
						return ERROR_DATE_HIGHER_THAN_FIXED;
					}
				} catch(exception $e) {
					// do nothing
				}
			} else if(keyIsTrue($definition, array("type_configuration", "max_date_time", "linked"))) {
				if(array_key_exists($definition["type_configuration"]["max_date_time"]["linked"], $formdata)) {
					try{
						$valDate = DateTime::createFromFormat($format, $value);

						$refValArr = is_array($formdata[$definition["type_configuration"]["max_date_time"]["linked"]]) ? $formdata[$definition["type_configuration"]["max_date_time"]["linked"]] : array($formdata[$definition["type_configuration"]["max_date_time"]["linked"]]);

						$refVal = array_filter(
							$refValArr, 
							array($this, "callback_isNotEmpty")
						);
						if(is_array($refVal)) {
							if(!$this->callback_isNotEmpty($refVal)) $refVal = array("");
							$refVal = min($refVal);
						}
						$referenceDate = DateTime::createFromFormat($format, $refVal);
						if($refVal == "" && $final) {
							return ERROR_REQUIRED;
						} else if($refVal != "" && $valDate > $referenceDate) {
							return ERROR_DATE_HIGHER_THAN_LINKED;
						}
					} catch(exception $e) {
						// do nothing
						var_dump($e);
					}
				}
			}
		}

		return OK;
	}
}