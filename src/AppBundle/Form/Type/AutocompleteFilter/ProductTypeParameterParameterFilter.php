<?php

namespace AppBundle\Form\Type\AutocompleteFilter;

use AppBundle\Component\AutocompleteFilter\AutocompleteFilterInterface;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class ProductTypeParameterParameterFilter implements AutocompleteFilterInterface
{
    /** @var null|Request */
    private $request;

    /** @var EntityManager */
    private $em;

    /**
     * AutocompleteFilterInterface constructor.
     *
     * @param EntityManager $em
     * @param RequestStack  $requestStack
     */
    public function __construct(EntityManager $em, RequestStack $requestStack)
    {
        $this->em = $em;
        $this->request = $requestStack->getCurrentRequest();
    }

    /**
     * @param QueryBuilder $qb
     * @param array        $data
     *
     * @return QueryBuilder|mixed
     */
    public function filter(QueryBuilder $qb, array $data = [])
    {
        $productTypeId = $this->request->getSession()->get('autocomplete_productType');
        //$groupId = $this->request->getSession()->get('autocomplete_group');
        //$type = $this->request->getSession()->get('autocomplete_type');

        $querybuilder = $this->em->createQueryBuilder();

        $sub = $querybuilder
            ->select('IDENTITY(p1.parameter)')
            ->from('AppBundle:Product\ProductType\ProductTypeParameter', 'p1')
            ->where($querybuilder->expr()->eq('p1.productType', ':productType'))
            ->andWhere($querybuilder->expr()->isNotNull('p1.parameter'));

        $qb->andWhere($querybuilder->expr()->notIn('entity.id', $sub->getDQL()))
            ->setParameter('productType', $productTypeId);

        return $qb;
    }
}
