<?php

/**
 * Cranleigh SNMP Check Wrapper Class
 *
 * @version 1.1 (14-Apr-2017)
 *
 * @author Fred Bradley <frb@cranleigh.org>
 * @link http://www.fredbradley.uk Fred Bradley
 * @example example/example_ups.php How to use the class
 */

/**
 * CranleighSNMPCheck class.
 */
class CranleighSNMPCheck
{

    /**
     * check_command
     *
     * (default value: "/usr/lib64/nagios/plugins/check_snmp_apcups")
     *
     * Abs Path to your check_snmp nagios plugin. (This is included in the git repo for completeness,
     * However it's recommended that you put it with all your other nagios plugins)
     *
     * @var string
     * @access private
     */
    private $check_command = "/usr/lib64/nagios/plugins/check_snmp_apcups";

    /**
     * community_name
     *
     * The community name that your UPS uses? Often defaulted to "public".
     * This is set in the __construct() function
     *
     * @var string
     * @access private
     */
    private $community_name;

    /**
     * load_percent
     *
     * @var int
     * @access public
     */
    public $load_percent;

    /**
     * runtime
     *
     * @var string
     * @access public
     */
    public $runtime;

    /**
     * css
     *
     * @var object
     * @access public
     */
    public $css;

    /**
     * states
     *
     * (default value: array())
     *
     * @var array
     * @access private
     */
    private $states = array();


    /**
     * __construct function.
     *
     * @access public
     * @param string $host (and ip4 address)
     * @param string $community_name (default: "public")
     * @return void
     */
    public function __construct($host, $community_name = "public")
    {
        $this->community_name = $community_name;

        $this->host = $host;

        $this->cssClasses();

        $this->showLoad = $this->getLoad();

        $this->showRuntime = $this->getRuntime();
    }

    /**
     * setNagiosState function.
     *
     * @access private
     * @param int $state
     * @return void
     */
    private function setNagiosState($state)
    {
        $this->states[] = $state;
    }

    /**
     * calcClass function.
     *
     * @access private
     * @return string (output of $this->css)
     */
    private function calcClass()
    {
        rsort($this->states);
        switch ($this->states[0]) :
            case 1:
                $output = $this->css->warning;
                break;
            case 2:
                $output = $this->css->critical;
                break;
            case 0:
                $output = $this->css->ok;
                break;
            default:
                $output = $this->css->unknown;
                break;
        endswitch;

        return $output;
    }

    /**
     * displayBlock function.
     *
     * @access public
     * @return string (The full nagios block for nagDash)
     */
    public function displayBlock()
    {
        if (($this->load_percent > 60) or ($this->getRuntimeInMinutes() < 30)) {
            $this->setNagiosState(1);
        } else {
            $this->setNagiosState(0);
        }

        if (($this->load_percent > 80) or ($this->getRuntimeInMinutes() < 20)) {
            $this->setNagiosState(2);
        } else {
            $this->setNagiosState(0);
        }

        $output = '<div class="ups '.$this->calcClass().'">';
        $output .= $this->showLoad."<br /><br />";
        $output .= $this->showRuntime;
        $output .= "</div>";

        return $output;
    }

    /**
     * cssClasses function.
     *
     * This sets the css classes that are returned at different Nagios warning levels.
     * @access private
     * @return void
     */
    private function cssClasses()
    {
        $class = new stdClass;
        $class->critical = "notok";
        $class->warning = "warning";
        $class->ok = "ok";
        $class->unknown = "unknown";

        $this->css = $class;
    }

    /**
     * snmp_check function.
     *
     * @access private
     * @param string $type
     * @return string
     */
    private function snmp_check($type)
    {
        $string = shell_exec($this->check_command." -C ".$this->community_name." -H ".$this->host);
        $type = strtoupper($type);
        switch ($type) :
            case "BATTERY":
            case "INPUT":
            case "OUTPUT":
            case "SELF TEST":
            case "LAST EVENT":
                $strlen = strlen($type)+2;
                $strpos = stripos($string, $type.":(");
                $string = substr($string, $strpos+$strlen);
                $first_close_bracket = stripos($string, ")");
                $string = substr($string, 0, $first_close_bracket);
                break;

            default:
                $string = "?";
                break;
        endswitch;
        return $string."<br />";
    }

    /**
     * getOutputSNMP function.
     *
     * @access private
     * @return object
     */
    private function getOutputSNMP()
    {

        $string = $this->snmp_check("output");

        $parts = new stdClass;
        $asArray = explode(",", $string);
        $parts->voltage = $this->tidyString($asArray[0]);
        $parts->frequency = $this->tidyString($asArray[1]);
        $parts->load = $this->tidyString($asArray[2]);

        return $parts;
    }

    /**
     * getBatterySNMP function.
     *
     * @access private
     * @return string from $this->snmp_check("battery")
     */
    private function getBatterySNMP()
    {

        $string = $this->snmp_check("battery");

        return $string;
    }

    /**
     * tidyString function.
     *
     * A helper function which can be used to perform generic tidying of the output strings
     *
     * @access private
     * @param string $string
     * @return string
     */
    private function tidyString($string)
    {

        return trim($string);
    }

    /**
     * getLoad function.
     *
     * @access private
     * @return string
     */
    private function getLoad()
    {
        $load = $this->getOutputSNMP()->load;
        $pos = stripos($load, "%");
        $parts = explode(" ", $load);

        $string = ucwords(substr($load, 0, $pos+1));

        $parts = explode(" ", $string);
        $this->load_percent = rtrim($parts[1], "%");
        return $parts[0].": ".$parts[1];
    }

    /**
     * getRuntime function.
     *
     * @access private
     * @return string
     */
    private function getRuntime()
    {
        $battery = $this->getBatterySNMP();
        $check = "runtime";
        $strlen = strlen($check);
        $pos = stripos($battery, $check);

        $string = substr($battery, $pos+$strlen);
        $pos = stripos($string, ",");

        $string = trim(substr($string, 0, $pos));
        $parts = explode(" ", trim($string));
        $this->runtime = $parts[0];
        $this->runtimeUnit = $this->convertTime($parts[1]);

        return "Runtime:<br />".$parts[0]." ".$this->convertTime($parts[1]);
    }

    /**
     * getRuntimeInMinutes function.
     *
     * For the check to work on the screen, we need to compute everything in Minutes
     *
     * @access private
     * @return void
     */
    private function getRuntimeInMinutes()
    {
        if ($this->runtimeUnit==="Mins") {
            return $this->runtime;
        }
        if ($this->runtimeUnit==="Hour") {
            return $this->runtime * 60;
        }
        return "0";
    }

    /**
     * convertTime function.
     *
     * Used to shorten the string output
     *
     * @access private
     * @param mixed $input
     * @return void
     */
    private function convertTime($input)
    {
        $input = strtolower($input);
        switch ($input) :
            case "minutes":
                $output = "Mins";
                break;
            case "seconds":
                $output = "Secs";
                break;
            default:
                $output = ucwords($input);
                break;
        endswitch;

        return $output;
    }
}
