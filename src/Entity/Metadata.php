<?php declare(strict_types=1);

namespace Reference\Entity;

use Omeka\Entity\Resource;
use Omeka\Entity\Value;

/**
 * Cache all localized values and linked resources of any property and
 * aggregated fields to simplify and speed up display.
 *
 * The main data to store are:
 * - the title of the value resources, that may be translated in English or any
 *   other languages, for example normalized or linked subjects;
 * - the core main fields (title and description), that depends on the template,
 *   in order to simplify and to speed up complex queries (list of references);
 * - to wide the concept of main field to any property and group of properties,
 *   for exemple to get the list of people, who can be creators of contributors.
 *
 * As an indirect benefit, numeric dates and other specific data types can be
 * localized quickly, and the resource template, class, item sets, etc. too.
 *
 * A value that is a value resource can be stored multiple times to get all
 * languages. A value that is a uri from a standardized ontology or repository
 * can be stored multiple times too via any module.
 *
 * This table is not designed for search queries, but for display, so the data
 * type of the content is useless. For the same reason, fields are stored as
 * strings, even when they are a property, to simplify common requests without
 * join. Of course, it is always possible to get the original value to get
 * additionnal data (property id, data type, value resource id, etc.).
 *
 * @todo Check if the text really need to be stored here for literal values, or
 * if the id of the final value (for linked resources with translated title) can
 * be used. Check should be done for sorting, extracting right translated value,
 * etc. Anyway, as most of the omeka databases are less than some a few dozen of
 * megabytes, it doesn't matter.
 *
 * @Entity
 * @Table(
 *     name="reference_metadata",
 *     indexes={
 *         @Index(
 *             name="idx_field",
 *             columns={"field"}
 *         ),
 *         @Index(
 *             name="idx_lang",
 *             columns={"lang"}
 *         ),
 *         @Index(
 *             name="idx_resource_field",
 *             columns={"resource_id", "field"}
 *         ),
 *         @Index(
 *             name="idx_text",
 *             columns={"text"},
 *             options={"lengths":{190}}
 *         )
 *     }
 * )
 */
class Metadata
{
    /**
     * @var int
     *
     * @Id
     * @Column(
     *     type="integer"
     * )
     * @GeneratedValue
     */
    protected $id;

    /**
     * @var \Omeka\Entity\Resource
     *
     * @ManyToOne(
     *     targetEntity="Omeka\Entity\Resource"
     * )
     * @JoinColumn(
     *     onDelete="cascade",
     *     nullable=false
     * )
     */
    protected $resource;

    /**
     * @var \Omeka\Entity\Value
     *
     * @ManyToOne(
     *     targetEntity="Omeka\Entity\Value"
     * )
     * @JoinColumn(
     *     onDelete="cascade",
     *     nullable=false
     * )
     */
    protected $value;

    /**
     * @var string
     *
     * @Column(
     *     length=190,
     *     nullable=false
     * )
     */
    protected $field;

    /**
     * @var string
     *
     * Unlike value entity, lang cannot be null to simplify querying.
     *
     * @Column(
     *     nullable=false,
     *     options={
     *         "default": ""
     *     }
     * )
     */
    protected $lang = '';

    /**
     * @var bool
     *
     * @Column(
     *     type="boolean",
     *     nullable=false,
     *     options={
     *         "default": 1
     *     }
     * )
     */
    protected $isPublic = true;

    /**
     * @var string
     *
     * @Column(
     *     type="text",
     *     nullable=false
     * )
     */
    protected $text;

    public function getId()
    {
        return $this->id;
    }

    public function setResource(Resource $resource): self
    {
        $this->resource = $resource;
        return $this;
    }

    public function getResource(): Resource
    {
        return $this->resource;
    }

    public function setValue(Value $value): self
    {
        $this->value = $value;
        return $this;
    }

    public function getValue(): Value
    {
        return $this->value;
    }

    public function setField(string $field): self
    {
        $this->field = $field;
        return $this;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function setLang($lang): self
    {
        $this->lang = (string) $lang;
        return $this;
    }

    public function getLang(): string
    {
        return $this->lang;
    }

    public function setIsPublic($isPublic): self
    {
        $this->isPublic = (bool) $isPublic;
        return $this;
    }

    public function getIsPublic(): bool
    {
        return (bool) $this->isPublic;
    }

    public function isPublic(): bool
    {
        return (bool) $this->isPublic;
    }

    public function setText($text): self
    {
        $this->text = (string) $text;
        return $this;
    }

    public function getText(): string
    {
        return $this->text;
    }
}
