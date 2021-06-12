<?php

namespace AppBundle\Model\Product\ProductType;

use AppBundle\Model\Parameter\Parameter;
use AppBundle\Model\Parameter\ParameterGroup;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class ProductTypeParameterRepository extends EntityRepository
{
    /**
     * @param \Doctrine\ORM\EntityManager $em
     */
    public function __construct(EntityManager $em)
    {
        $classMetadata = $em->getClassMetadata(ProductTypeParameter::class);
        parent::__construct($em, $classMetadata);
    }

    /**
     * @param ProductType    $productType
     * @param ParameterGroup $group
     *
     * @return ProductTypeParameter|null
     */
    public function getLastParameterInGroup(ProductType $productType, ParameterGroup $group)
    {
        /** @var ProductTypeParameter|null $parameter */
        $parameter = $this->findOneBy(['productType' => $productType, 'parameterGroup' => $group], ['position' => 'DESC']);

        return $parameter;
    }

    /**
     * @param ProductType    $productType
     * @param ParameterGroup $group
     * @param string         $sort
     *
     * @return ProductTypeParameter[]
     */
    public function getParametersInGroup(ProductType $productType, ParameterGroup $group, $sort = 'ASC'): array
    {
        return $this->findBy(['productType' => $productType, 'parameterGroup' => $group], ['position' => $sort]);
    }

    /**
     * @param ProductType $productType
     *
     * @return null|int
     */
    public function getLastPosition(ProductType $productType)
    {
        /** @var ProductTypeParameter|null $parameter */
        $parameter = $this->findOneBy(['productType' => $productType], ['position' => 'DESC']);

        if ($parameter) {
            return $parameter->getPosition();
        }

        return null;
    }

    /**
     * @param ProductType    $productType
     * @param ParameterGroup $group
     *
     * @return int|null
     */
    public function getLastPositionInGroup(ProductType $productType, ParameterGroup $group)
    {
        $parameter = $this->getLastParameterInGroup($productType, $group);
        if ($parameter) {
            return $parameter->getPosition();
        }

        return null;
    }

    /**
     * Zjistí, jestli je daná Entita hlavní skupinou.
     *
     * @param ProductTypeParameter $entity
     *
     * @return bool|null
     */
    public function isGroup(ProductTypeParameter $entity)
    {
        return $entity->getParameterGroup() && $entity->getParameter() === null;
    }

    /**
     * Pro danou entitu Parametru vrací ID (ProductTypeParametr) skupiny, pod kterou parametr náleží.
     *
     * @param ProductTypeParameter $entity
     *
     * @return int|null
     */
    public function getGroupIdForParameter(ProductTypeParameter $entity)
    {
        $groupEntity = $this->getGroupForParameter($entity);
        if ($groupEntity) {
            return $groupEntity->getId();
        }

        return null;
    }

    /**
     * Pro danou entitu Parametru vrací entitu skupiny (ProductTypeParameter), pod kterou tento parametr patří.
     *
     * @param ProductTypeParameter $entity
     *
     * @return null|ProductTypeParameter
     */
    public function getGroupForParameter(ProductTypeParameter $entity)
    {
        /** @var ProductTypeParameter|null $groupEntity */
        $groupEntity = $this->findOneBy([
            'productType' => $entity->getProductType(),
            'parameterGroup' => $entity->getParameterGroup(),
            'parameter' => null,
            ]);

        return $groupEntity;
    }

    /**
     * @param int $productTypeId
     *
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function getProductTypeParametersForProductTypeQuery(int $productTypeId): QueryBuilder
    {
        $qb = $this->_em->createQueryBuilder();
        $qb->select('ptp')
            ->from(ProductTypeParameter::class, 'ptp')
            ->distinct()
            ->where($qb->expr()->eq('ptp.productType', ':productType'))
            ->andWhere($qb->expr()->isNotNull('ptp.parameter'))
            ->setParameter('productType', $productTypeId);

        return $qb;
    }

    /**
     * Vrátí pole entit všech použitých parametrů v daném ProduktTypu, popřípadě prázdné pole, pokud není žádný nalezen.
     *
     * @param ProductType $productType
     *
     * @return Parameter[]
     */
    public function getDistinctParametersForProductType(ProductType $productType): array
    {
        $qb = $this->getProductTypeParametersForProductTypeQuery($productType->getId());
        $qb->orderBy('ptp.parameter', 'ASC');

        /** @var ProductTypeParameter[] $productTypeParameters */
        $productTypeParameters = $qb->getQuery()->getResult();
        $parameters = [];
        foreach ($productTypeParameters as $item) {
            $parameters[] = $item->getParameter();
        }

        return $parameters;
    }

    /**
     * @param int $productTypeId
     *
     * @return \AppBundle\Model\Product\ProductType\ProductTypeParameter[]|array
     */
    public function getProductTypeParametersForProductType(int $productTypeId): array
    {
        $qb = $this->getProductTypeParametersForProductTypeQuery($productTypeId);
        $qb->orderBy('ptp.position', 'ASC');

        return $qb->getQuery()->getResult();
    }

    /**
     * @param $parameterGroup
     *
     * @return array|null
     */
    public function getLastUsedGroupParameters($parameterGroup)
    {
        $qbsub = $this->_em->createQueryBuilder();
        $qb = $this->_em->createQueryBuilder();

        $sub = $qbsub->select('IDENTITY(gp.productType)')
            ->from('AppBundle:Product\ProductType\ProductTypeParameter', 'gp')
            ->where($qbsub->expr()->eq('gp.parameterGroup', ':parameterGroup'))
            ->orderBy('gp.updated', 'DESC')
            ->setMaxResults(1)
            ->setParameter('parameterGroup', $parameterGroup);
        $subResult = $sub->getQuery()->getResult();

        if (empty($subResult)) {
            return null;
        }

        $query = $qb->select('p')
            ->from('AppBundle:Product\ProductType\ProductTypeParameter', 'p')
            ->where($qb->expr()->eq('p.parameterGroup', ':parameterGroup'))
            ->andWhere($qb->expr()->eq('p.productType', $subResult[0][1]))
            ->andWhere($qb->expr()->isNotNull('p.parameter'))
            ->orderBy('p.position', 'ASC')
            ->setParameter('parameterGroup', $parameterGroup);

        return $query->getQuery()->getResult();
    }
}
