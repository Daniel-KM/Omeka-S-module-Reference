<?php
namespace Reference\Form;

use Omeka\Stdlib\Message;
use Omeka\Api\Manager as ApiManager;
use Zend\Form\Element\Textarea;
use Zend\Form\Form;

class ConfigForm extends Form
{
    /**
     * @var ApiManager
     */
    protected $api;

    public function init()
    {
        $this->setAttribute('id', 'config-form');

        $this->add([
            'type' => 'Fieldset',
            'name' => 'fieldset_reference_general',
            'options' => [
                'label' => 'Reference', // @translate
                'info' => 'Most of these options for list and for tree can be overridden in the theme.', // @translate
                'description' => 'QSDFGHJKJHGFDS.', // @translate
            ],
            'description' => 'ZERTYUIOPOIUYT.', // @translate
        ]);

        $this->add([
            'name' => 'fieldset_reference_list',
            'type' => 'Fieldset',
            'options' => [
                'label' => 'References indexes', // @translate
            ],
        ]);
        $referenceFieldset = $this->get('fieldset_reference_list');

        foreach ([
            'resource_classes' => 'Resource classes', // @translate
            'properties' => 'Properties', // @translate
        ]
            as $type => $label
        ) {
            $referenceFieldset->add([
                'name' => 'type' ,
                'type' => 'Fieldset',
                'options' => [
                    'label' => $label, // @translate
                ],
            ]);
            $typeFieldset = $referenceFieldset->get('type');

            $list = $this->prepareList($type);
            foreach ($list as $member) {
            }
        }
        /*
                <div class="field">
                <div>
                <?php echo $this->formLabel('reference_slugs', __('Display References')); ?>
                </div>
                <div class="inputs">
                    <p class="explanation">
                        <?php echo __('Select the elements to display and define a slug so the references will be available at "references/:slug".'); ?>
                        <?php echo __('Slugs should be single.'); ?>
                    </p>
                    <table id="hide-elements-table">
                        <thead>
                            <tr>
                                <th class="reference-boxes"><?php echo __('Item Type / Element'); ?></th>
                                <th class="reference-boxes"><?php echo __('Display'); ?></th>
                                <th class="reference-boxes"><?php echo __('Slug'); ?></th>
                                <th class="reference-boxes"><?php echo __('Label'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $current_type = null;
                        $current_element_set = null;
                        foreach ($slugs as $slug => $slugData):
                            $record = $tables[$slugData['type']]->find($slugData['id']);
                            if (empty($record)) {
                                continue;
                            }
                            $idKey = '[' . $slugData['type'] . '][' . $slugData['id'] . ']';
                            if ($slugData['type'] !== $current_type):
                                $current_type = $slugData['type'];
                            ?>
                            <tr>
                                <th colspan="4">
                                    <strong><?php echo Inflector::humanize($current_type, 'all'); ?></strong>
                                </th>
                            </tr>
                            <?php endif;
                            if ($current_type == 'Element'):
                                if ($record->set_name != $current_element_set):
                                    $current_element_set = $record->set_name; ?>
                            <tr>
                                <th colspan="4">
                                    <strong><?php echo $current_element_set; ?></strong>
                                </th>
                            </tr>
                                <?php endif;
                            endif; ?>
                            <tr>
                                <td><?php echo $record->name; ?></td>
                                <td class="reference-boxes">
                                    <?php echo $this->formCheckbox(
                                        'actives' . $idKey,
                                        true,
                                        array('checked' => (boolean) $slugData['active'])
                                    ); ?>
                                </td>
                                <td class="reference-boxes">
                                    <?php echo $this->formText(
                                        'slugs' . $idKey,
                                        $slug, null); ?>
                                </td>
                                <td class="reference-boxes">
                                    <?php echo $this->formText(
                                        'labels' . $idKey,
                                        $slugData['label'], null); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        */

        $this->add([
            'name' => 'reference_list_skiplinks',
            'type' => \Zend\Form\Element\Checkbox::class,
            'options' => [
                'label' => 'Print skip links', // @translate
                'info' => new Message('Print skip links at the top and bottom of each page, which link to the alphabetical headers.') // @translate
                    . ' ' . new Message('Note that if headers are turned off, skiplinks do not work.'), // @translate
            ],
        ]);

        $this->add([
            'name' => 'reference_list_headings',
            'type' => \Zend\Form\Element\Checkbox::class,
            'options' => [
                'label' => 'Print headings', // @translate
                'info' => 'Print headers for each section (#0-9 and symbols, A, B, etc.).', // @translate
            ],
        ]);

        $this->add([
            'name' => 'reference_query_type',
            'type' => \Zend\Form\Element\Radio::class,
            'options' => [
                'label' => 'Query type', // @translate
                'info' => 'The type of query defines how elements are regrouped (see the advanced search).', // @translate
                'value_options' => [
                    'eq' => 'Is Exactly', // @translate
                    'in' => 'Contains', // @translate
                ],
            ],
        ]);

        $this->add([
            'name' => 'reference_link_to_single',
            'type' => \Zend\Form\Element\Checkbox::class,
            'options' => [
                'label' => 'Link to single records', // @translate
                'info' => 'When a reference has only one item, link to it directly instead of to the items/browse page.', // @translate
            ],
        ]);

        $this->add([
            'type' => 'Fieldset',
            'name' => 'fieldset_reference_tree',
            'options' => [
                'label' => 'Hierarchy of subjects', // @translate
            ],
        ]);

        $this->add([
            'name' => 'reference_tree_enabled',
            'type' => \Zend\Form\Element\Checkbox::class,
            'options' => [
                'label' => 'Enable tree view', // @translate
                'info' => new Message('Enable the page and display the link "%s" to the hierarchical view in the navigation bar.', // @translate
                    '/subjects/tree'),
            ],
        ]);

        $this->add([
            'name' => 'reference_tree_expanded',
            'type' => \Zend\Form\Element\Checkbox::class,
            'options' => [
                'label' => 'Expand tree', // @translate
                'info' => 'Check this box to display the tree expanded. This option can be overridden by the theme.', // @translate
            ],
        ]);

        $this->add([
            'name' => 'reference_tree_hierarchy',
            'type' => TextArea::class,
            'options' => [
                'label' => 'Set the hierarchy of subjects', // @translate
                'info' => new Message('If any, write the hierarchy of all your subjects in order to display them in the "Hierarchy of Subjects" page.') // @translate
                    . ' ' . new Message('Format is: one subjet by line, preceded by zero, one or more "-" to indicate the hierarchy level.') // @translate
                    . ' ' . new Message('Separate the "-" and the subject with a space. Empty lines are not considered.'), // @translate
            ],
            'attributes' => [
                'rows' => 20,
                'cols' => 60,
                // The place holder can't use end of line, so a symbol
                // is used for it.
                'placeholder' => '
Europe ↵
- France ↵
-- Paris ↵
-- Lyon ↵
-- Marseille ↵
- United Kingdom ↵
-- London ↵
-- Manchester ↵
Asia ↵
',
            ],
        ]);
    }

    /**
     * Prepare a list of entities.
     *
     * @see \Omeka\Form\Element\AbstractVocabularyMemberSelect::getValueOptions()
     *
     * @param string $resourceName
     */
    protected function prepareList($resourceName)
    {
        $termAsValue = true;

        $query = [];
        $query['sort_by'] = 'label';

        $valueOptions = [];
        $response = $this->getApiManager()->search($resourceName, $query);
        foreach ($response->getContent() as $member) {
            $attributes = ['data-term' => $member->term()];
            if ('properties' === $resourceName) {
                $attributes['data-property-id'] = $member->id();
            } elseif ('resource_classes' === $resourceName) {
                $attributes['data-resource-class-id'] = $member->id();
            }
            $option = [
                'label' => $member->label(),
                'value' => $termAsValue ? $member->term() : $member->id(),
                'attributes' => $attributes,
            ];
            $vocabulary = $member->vocabulary();
            if (!isset($valueOptions[$vocabulary->prefix()])) {
                $valueOptions[$vocabulary->prefix()] = [
                    'label' => $vocabulary->label(),
                    'options' => [],
                ];
            }
            $valueOptions[$vocabulary->prefix()]['options'][] = $option;
        }

        // Move Dublin Core vocabularies (dcterms & dctype) to the beginning.
        if (isset($valueOptions['dcterms'])) {
            $valueOptions = ['dcterms' => $valueOptions['dcterms']] + $valueOptions;
        }
        if (isset($valueOptions['dctype'])) {
            $valueOptions = ['dctype' => $valueOptions['dctype']] + $valueOptions;
        }

        return $valueOptions;
    }

    public function setApiManager(ApiManager $api)
    {
        $this->api = $api;
    }

    public function getApiManager()
    {
        return $this->api;
    }
}
