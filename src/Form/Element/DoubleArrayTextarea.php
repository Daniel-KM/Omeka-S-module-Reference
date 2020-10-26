<?php declare(strict_types=1);

namespace Reference\Form\Element;

use Omeka\Form\Element\ArrayTextarea;

class DoubleArrayTextarea extends ArrayTextarea
{
    /**
     * @var array|null
     */
    protected $secondLevelKeys;

    /**
     * @param array $options
     */
    public function setOptions($options)
    {
        parent::setOptions($options);
        if (array_key_exists('second_level_keys', $this->options)) {
            $this->setSecondLevelKeys($this->options['second_level_keys']);
        }
        return $this;
    }

    public function arrayToString($array)
    {
        if (is_string($array)) {
            return $array;
        }
        $string = '';
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $string .= $this->asKeyValue ? $key : '';
                foreach ($value as $val) {
                    $string .= " $this->keyValueSeparator $val";
                }
                $string .= "\n";
            } elseif (strlen((string) $value)) {
                if ($this->asKeyValue) {
                    $string .= "$key ";
                }
                $string .= "$this->keyValueSeparator $value\n";
            } elseif ($this->asKeyValue) {
                $string .= $key . "\n";
            }
        }
        return $string;
    }

    public function stringToArray($string)
    {
        if (is_array($string)) {
            return $string;
        }
        $values = parent::stringToArray($string);
        if (is_null($this->secondLevelKeys)) {
            return $values;
        }
        $limit = count($this->secondLevelKeys);
        if ($limit) {
            foreach ($values as $key => $value) {
                $value = array_map('trim', explode($this->keyValueSeparator, $value, $limit));
                $values[$key] = array_combine($this->secondLevelKeys, array_pad($value, $limit, null));
            }
        } else {
            foreach ($values as $key => $value) {
                $values[$key] = array_map('trim', explode($this->keyValueSeparator, $value));
            }
        }
        return $values;
    }

    /**
     * Set the option to associate a key to second level values.
     *
     * @param array|null $secondLevelKeys If null, keep it as string.
     * @return self
     */
    public function setSecondLevelKeys(array $secondLevelKeys = null)
    {
        $this->secondLevelKeys = $secondLevelKeys;
        return $this;
    }

    /**
     * Get the option to associate a key to second level values.
     *
     * @return string
     */
    public function getSecondLevelKeys()
    {
        return $this->secondLevelKeys;
    }
}
