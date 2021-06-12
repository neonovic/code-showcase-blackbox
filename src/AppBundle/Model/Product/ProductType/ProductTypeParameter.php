<?php

namespace AppBundle\Model\Product\ProductType;

use AppBundle\Model\Parameter\Parameter;
use AppBundle\Model\Parameter\ParameterGroup;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Index;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Table(indexes={
 *      @Index(name="producttypeparameter_product_type_id_idx", columns={"product_type_id"}),
 *      @Index(name="producttypeparameter_parameter_group_id_idx", columns={"parameter_group_id"}),
 *      @Index(name="producttypeparameter_parameter_id_idx", columns={"parameter_id"})
 * }, uniqueConstraints={
 *      @UniqueConstraint(name="ptparameter_ptid_parametergroupid_parameterid",
 *          columns={"product_type_id", "parameter_group_id", "parameter_id"})
 * })
 * @ORM\Entity(repositoryClass="AppBundle\Model\Product\ProductType\ProductTypeParameterRepository")
 */
class ProductTypeParameter
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @var ProductType|null
     *
     * @ORM\ManyToOne(targetEntity="AppBundle\Model\Product\ProductType\ProductType", inversedBy="productTypeParameters")
     * @ORM\JoinColumn(name="product_type_id", referencedColumnName="id", nullable=false)
     * @Assert\NotNull()
     */
    private $productType;

    /**
     * @var ParameterGroup|null
     *
     * @ORM\ManyToOne(targetEntity="AppBundle\Model\Parameter\ParameterGroup")
     * @ORM\JoinColumn(name="parameter_group_id", referencedColumnName="id", nullable=true)
     * @Assert\NotBlank(groups={"group", "groupedparameter"})
     */
    private $parameterGroup;

    /**
     * @var Parameter|null
     *
     * @ORM\ManyToOne(targetEntity="AppBundle\Model\Parameter\Parameter")
     * @ORM\JoinColumn(name="parameter_id", referencedColumnName="id", nullable=true)
     * @Assert\NotBlank(groups={"parameter", "groupedparameter"})
     */
    private $parameter;

    /**
     * @ORM\Column(type="smallint", nullable=false, options={"default": 0})
     * @Assert\NotNull()
     * @Assert\Range(
     *      min = 0,
     *      max = 32767,
     *      minMessage = "smallint.min.value",
     *      maxMessage = "smallint.max.value"
     * )
     */
    private $position = 0;

    /**
     * @ORM\Column(type="boolean", nullable=false, options={"default" : false})
     */
    private $required = false;

    /**
     * @ORM\Column(type="boolean", nullable=false, options={"default" : false})
     */
    private $filter = false;

    /**
     * @ORM\Column(type="boolean", nullable=false, options={"default" : false})
     */
    private $collapsed = false;

    /**
     * @ORM\Column(type="boolean", nullable=false, options={"default" : false})
     */
    private $displayNegativeValue = false;

    /**
     * @var \DateTime|null
     *
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(name="created", type="datetime")
     */
    private $created;

    /**
     * @var \DateTime|null
     *
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(name="updated", type="datetime")
     */
    private $updated;

    private $copyParameterGroup;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return ProductType|null
     */
    public function getProductType()
    {
        return $this->productType;
    }

    /**
     * @param ProductType|null $productType
     */
    public function setProductType($productType)
    {
        $this->productType = $productType;
    }

    /**
     * @return ParameterGroup|null
     */
    public function getParameterGroup()
    {
        return $this->parameterGroup;
    }

    /**
     * @param ParameterGroup|null $parameterGroup
     */
    public function setParameterGroup($parameterGroup)
    {
        $this->parameterGroup = $parameterGroup;
    }

    /**
     * @return Parameter|null
     */
    public function getParameter()
    {
        return $this->parameter;
    }

    /**
     * @param Parameter|null $parameter
     */
    public function setParameter($parameter)
    {
        $this->parameter = $parameter;
    }

    /**
     * @return mixed
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     * @param mixed $position
     */
    public function setPosition($position)
    {
        $this->position = $position;
    }

    /**
     * @return mixed
     */
    public function getRequired()
    {
        return $this->required;
    }

    /**
     * @param mixed $required
     */
    public function setRequired($required)
    {
        $this->required = $required;
    }

    /**
     * @return mixed
     */
    public function getFilter()
    {
        return $this->filter;
    }

    /**
     * @param mixed $filter
     */
    public function setFilter($filter)
    {
        $this->filter = $filter;
    }

    /**
     * @return mixed
     */
    public function getCollapsed()
    {
        return $this->collapsed;
    }

    /**
     * @param mixed $collapsed
     */
    public function setCollapsed($collapsed)
    {
        $this->collapsed = $collapsed;
    }

    /**
     * @return mixed
     */
    public function getDisplayNegativeValue()
    {
        return $this->displayNegativeValue;
    }

    /**
     * @param mixed $displayNegativeValue
     */
    public function setDisplayNegativeValue($displayNegativeValue)
    {
        $this->displayNegativeValue = $displayNegativeValue;
    }

    /**
     * @return \DateTime|null
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * @param \DateTime $created
     */
    public function setCreated(\DateTime $created)
    {
        $this->created = $created;
    }

    /**
     * @return \DateTime|null
     */
    public function getUpdated()
    {
        return $this->updated;
    }

    /**
     * @param \DateTime $updated
     */
    public function setUpdated(\DateTime $updated)
    {
        $this->updated = $updated;
    }

    /**
     * @param mixed $copyParameterGroup
     */
    public function setCopyParameterGroup($copyParameterGroup)
    {
        $this->copyParameterGroup = $copyParameterGroup;
    }

    /**
     * @return mixed
     */
    public function getCopyParameterGroup()
    {
        return $this->copyParameterGroup;
    }
}
