<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagImportExport\Components\Transformers;

/**
 * The responsibility of this class is to modify the values of the data values due to given user small scripts.
 */
class ValuesTransformer implements DataTransformerAdapter
{
    /**
     * @var iterable<object>
     */
    private ?iterable $config = null;

    private ?ExpressionEvaluator $evaluator;

    /**
     * The $config must contain the smarty or php transformation of values.
     */
    public function initialize($config)
    {
        $this->config = $config['expression'];
        $this->evaluator = $config['evaluator'];
    }

    /**
     * Maps the values by using the config export smarty fields and returns the new array
     *
     * @param array $data
     *
     * @return array
     */
    public function transformForward($data)
    {
        $data = $this->transform('export', $data);

        return $data;
    }

    /**
     * Changes and returns the new values, before importing
     *
     * @param array $data
     *
     * @return array
     */
    public function transformBackward($data)
    {
        $data = $this->transform('import', $data);

        return $data;
    }

    /**
     * @param string $type
     * @param array  $data
     *
     * @throws \Exception
     *
     * @return array
     */
    public function transform($type, $data)
    {
        $conversions = [];

        switch ($type) {
            case 'export':
                $method = 'getExportConversion';
                break;
            case 'import':
                $method = 'getImportConversion';
                break;
            default:
                throw new \Exception("Convert type $type does not exist.");
        }

        if (!\is_array($this->config)) {
            return $data;
        }

        foreach ($this->config as $expression) {
            $conversions[$expression->getVariable()] = $expression->{$method}();
        }

        if (!empty($conversions)) {
            foreach ($data as &$records) {
                foreach ($records as &$record) {
                    foreach ($conversions as $variableName => $conversion) {
                        if (!$this->evaluator) {
                            throw new \Exception('Evaluator is not set');
                        }

                        if (isset($record[$variableName]) && !empty($conversion)) {
                            $evalData = $this->evaluator->evaluate($conversion, $record);
                            if ($evalData || $evalData === '0') {
                                $record[$variableName] = $evalData;
                            }
                        }
                    }
                }
            }
        }

        return $data;
    }
}
