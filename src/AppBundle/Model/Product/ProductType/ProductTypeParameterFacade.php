<?php

namespace AppBundle\Model\Product\ProductType;

use AppBundle\Model\Parameter\ParameterGroup;
use AppBundle\Model\Product\ProductType\Exception\ProductTypeParameterNotFound;
use Doctrine\ORM\EntityManager;

class ProductTypeParameterFacade
{
    /** @var EntityManager */
    private $em;

    /** @var ProductTypeParameterRepository */
    private $productTypeParameterRepository;

    /**
     * Právě přesouvaný objekt.
     *
     * @var ProductTypeParameter
     */
    private $current;

    /**
     * Nově přesouvaný objekt je umístěn ihned za tento 'previous' objekt (position = previous + 1).
     *
     * @var ProductTypeParameter
     */
    private $previous;

    /**
     * Je přesouvaný objekt samotná skupina?
     *
     * @var bool
     */
    private $isGroup;

    /**
     * Do které skupiny je objekt nově přesunut. Null = žádná skupina.
     *
     * @var ParameterGroup|null
     */
    private $newGroup;

    /**
     * Stará position, ze které je právě přesouvaný objekt posouván. Tedy jeho startovací position.
     *
     * @var int
     */
    private $currentPosition;

    /**
     * Position, na kterou se má objekt nově přesunout. Null, pokud umísťujeme na první místo.
     *
     * @var int|null
     */
    private $newPosition;

    /** @var ProductType */
    private $productType;

    /**
     * Směr, kterým se posouvaný objekt vizuelně pohybuje. Pokud jej přetahujem ze spodu nahoru, tak 'up', jinak 'down'.
     *  Paradoxně se ale hodnota 'position' zmenšuje, když jdeme 'up' a opačně. Ale takto mi to příjde více intuitivní.
     *
     * @var string
     */
    private $direction;

    /**
     * @param EntityManager $em
     */
    public function __construct(
        EntityManager $em
    ) {
        $this->em = $em;
        $this->productTypeParameterRepository = $em->getRepository(ProductTypeParameter::class);
    }

    /**
     * Inicializace hodnot pro javascriptové (camohub) přesunutí parametru (skupiny).
     *
     * @param $currentId int ProductTypeParameter id aktuální, přesouvaného prvku
     * @param $prevId int ProductTypeParameter id přímo předchozího prvku (<li>) v camohubu
     * @param $parentId int ProductTypeParameter id skupiny, ve které prvek popřípadě je
     */
    public function initMove(int $currentId, int $prevId, int $parentId)
    {
        $this->current = $this->productTypeParameterRepository->find($currentId);
        $this->previous = $this->getPreviousProductTypeParameter($parentId, $prevId);
        $this->isGroup = !$this->current->getParameter();
        $this->newGroup = $this->getProductTypeParameterParameterGroup($parentId);
        $this->currentPosition = $this->current->getPosition();
        if ($this->previous) {
            if ($this->currentPosition < $this->previous->getPosition()) {
                $this->direction = 'down';
                $this->newPosition = $this->previous->getPosition();
            } elseif ($this->currentPosition > $this->previous->getPosition()) {
                $this->direction = 'up';
                $this->newPosition = $this->previous->getPosition() + 1;
            } else {
                $this->direction = 'stay';
                $this->newPosition = $this->previous->getPosition();
            }
        } else {
            $this->newPosition = 1;
        }
        $this->productType = $this->current->getProductType();
    }

    /**
     * @param ProductType $productType
     */
    public function setProductType(ProductType $productType)
    {
        $this->productType = $productType;
    }

    /**
     * @throws \Exception
     *
     * @return bool
     */
    public function processMove(): bool
    {
        try {
            $this->em->getConnection()->beginTransaction();

            if ($this->isGroup) {
                $this->moveProductTypeParameterGroup($this->productType, $this->currentPosition, $this->newPosition);
            } else {
                $this->moveProductTypeParameterSingleParameter($this->productType, $this->currentPosition, $this->newPosition, $this->newGroup);
            }

            $this->em->flush();
            $this->em->getConnection()->commit();
            $result = true;
        } catch (\Exception $e) {
            $this->em->getConnection()->rollBack();
            throw $e;
        }

        return $result;
    }

    /**
     * Parametry se zadávají referencí na hodnotu jejich 'position'.
     *   Přesouvá tedy parametry s hodnotou pozic $from až $to.
     *
     * @param ProductType $productType
     * @param int $from 'Position' prvního prvku
     * @param $to int|null 'Position' posledního prvku
     * @param int $value O kolik se má snížit 'position'
     *
     * @throws ProductTypeParameterNotFound
     */
    public function moveProductTypeParametersUp(ProductType $productType, int $from, $to = null, int $value = 1)
    {
        $to = $to ?? $this->productTypeParameterRepository->getLastPosition($productType);
        $to = $to ?? 0;
        for ($i = $from; $i <= $to; ++$i) {
            /** @var ProductTypeParameter $productTypeParameter */
            $productTypeParameter = $this->productTypeParameterRepository->findOneBy(['productType' => $productType, 'position' => $i]);
            if (!$productTypeParameter) {
                throw new ProductTypeParameterNotFound(
                    'ProcutType id: ' . $productType . ' and position: ' . $i . ' not found in ProductTypeParameter'
                );
            }
            $productTypeParameter->setPosition($i - $value);
        }
    }

    /**
     * Parametry se zadávají referencí na hodnotu jejich 'position'.
     *   Přesouvá tedy parametry s hodnotou pozic $from až $to.
     *
     * @param ProductType $productType
     * @param $from int 'Position' prvního prvku
     * @param $to int|null 'Position' posledního prvku, když null (default), tak se myslí všechny prvky až do posledního
     * @param int $value O kolik se má zvýšit 'position'
     *
     * @throws ProductTypeParameterNotFound
     */
    public function moveProductTypeParametersDown(ProductType $productType, int $from, $to = null, int $value = 1)
    {
        $to = $to ?? $this->productTypeParameterRepository->getLastPosition($productType) ?? 0;
        for ($i = $to; $i >= $from; --$i) {
            /** @var ProductTypeParameter $productTypeParameter */
            $productTypeParameter = $this->productTypeParameterRepository->findOneBy(['productType' => $productType, 'position' => $i]);
            if (!$productTypeParameter) {
                throw new ProductTypeParameterNotFound(
                    'ProcutType id: ' . $productType . ' and position: ' . $i . ' not found in ProductTypeParameter'
                );
            }
            $productTypeParameter->setPosition($i + $value);
        }
    }

    /**
     * @param ProductTypeParameter $entity
     */
    public function deleteProductTypeParameterEntity(ProductTypeParameter $entity)
    {
        if ($this->productTypeParameterRepository->isGroup($entity)) {
            $this->deleteProductTypeParameterGroup($entity);
        } else {
            $this->deleteProductTypeParameterSingleParameter($entity);
        }
    }

    /**
     * @param ProductTypeParameter $entity
     */
    public function deleteProductTypeParameterGroup(ProductTypeParameter $entity)
    {
        $productTypeParameters = $this->productTypeParameterRepository->getParametersInGroup($entity->getProductType(), $entity->getParameterGroup());
        $lastParameter = end($productTypeParameters);
        $count = count($productTypeParameters);
        $this->moveProductTypeParametersUp($entity->getProductType(), $lastParameter->getPosition() + 1, null, $count);
        foreach ($productTypeParameters as $parameter) {
            $this->em->remove($parameter);
        }
    }

    /**
     * @param ProductTypeParameter $entity
     */
    protected function deleteProductTypeParameterSingleParameter(ProductTypeParameter $entity)
    {
        $this->moveProductTypeParametersUp($entity->getProductType(), $entity->getPosition() + 1);
        $this->em->remove($entity);
    }

    /**
     * @param $parentId int ProductTypeParameter id
     * @param $prevId int ProductTypeParameter id
     *
     * @return ProductTypeParameter|null
     */
    private function getPreviousProductTypeParameter(int $parentId, int $prevId)
    {
        if ($prevId === 0) {
            // umístění na první pozici
            $previousParameter = null;
            // umístění na první pozici v kategorii
            if ($parentId !== 0) {
                $previousParameter = $this->productTypeParameterRepository->find($parentId);
            }
        } else {
            $previousEntity = $this->productTypeParameterRepository->find($prevId);
            $previousParameter = $previousEntity;
            // umístění ihned za nějakou kategorií, musím zjistit ID posledního v kategorii
            if ($parentId === 0 && $previousEntity->getParameterGroup()) {
                $previousParameter = $this->productTypeParameterRepository->getLastParameterInGroup(
                    $this->current->getProductType(),
                    $previousEntity->getParameterGroup()
                );
            }
        }

        return $previousParameter;
    }

    /**
     * @param $productTypeParameterId
     *
     * @return ParameterGroup|null
     */
    private function getProductTypeParameterParameterGroup($productTypeParameterId)
    {
        $parameterGroup = null;
        if ($productTypeParameterId) {
            $parameterGroup = $this->productTypeParameterRepository->find($productTypeParameterId)->getParameterGroup();
        }

        return $parameterGroup;
    }

    /**
     * Přemístí parametr z 'oldPosition' na 'newPosition'.
     *
     * @param ProductType $productType
     * @param int $oldPosition
     * @param int $newPosition
     * @param ParameterGroup|null $parameterGroup
     *
     * @throws ProductTypeParameterNotFound
     */
    public function moveProductTypeParameterSingleParameter(ProductType $productType, $oldPosition, $newPosition, ParameterGroup $parameterGroup = null)
    {
        $productTypeParameter = $this->productTypeParameterRepository
            ->findOneBy(['productType' => $productType, 'position' => $oldPosition]);
        if (!$productTypeParameter) {
            throw new ProductTypeParameterNotFound('ProcutTypeParameter not found, productType: ' . $productType . ', position: ' . $oldPosition);
        }
        $productTypeParameter->setPosition(999);
        if ($oldPosition < $newPosition) {
            $this->moveProductTypeParametersUp($productType, $oldPosition + 1, $newPosition);
        } elseif ($oldPosition > $newPosition) {
            $this->moveProductTypeParametersDown($productType, $newPosition, $oldPosition - 1);
        }
        $productTypeParameter->setPosition($newPosition);
        $productTypeParameter->setParameterGroup($parameterGroup);
        $this->em->flush();
    }

    /**
     * Přemístí celou skupinu, včetně jejích parametrů. 'oldPosition' určuje pozici samotného parametru skupiny.
     *
     * @param ProductType $productType
     * @param $oldPosition
     * @param $newPosition
     *
     * @throws ProductTypeParameterNotFound
     */
    public function moveProductTypeParameterGroup(ProductType $productType, $oldPosition, $newPosition)
    {
        $productTypeParameter = $this->productTypeParameterRepository->findOneBy(['productType' => $productType, 'position' => $oldPosition]);
        if (!$productTypeParameter) {
            throw new ProductTypeParameterNotFound('ProcutTypeParameter not found, productType: ' . $productType . ', position: ' . $oldPosition);
        }
        $parameterGroup = $productTypeParameter->getParameterGroup();
        $productTypeParameters = [];
        if ($oldPosition < $newPosition) {
            $productTypeParameters = $this->productTypeParameterRepository->getParametersInGroup($productType, $parameterGroup, 'DESC');
        } elseif ($oldPosition > $newPosition) {
            $productTypeParameters = $this->productTypeParameterRepository->getParametersInGroup($productType, $parameterGroup, 'ASC');
        }

        /** @var ProductTypeParameter $productTypeParameter */
        foreach ($productTypeParameters as $productTypeParameter) {
            $this->moveProductTypeParameterSingleParameter($this->productType, $productTypeParameter->getPosition(), $newPosition, $parameterGroup);
            if ($this->direction === 'down') {
                --$newPosition;
            } else {
                ++$newPosition;
            }
        }
    }

    /**
     * Pro daný 'productType' vrací array všech product_type_parametrů seřazených podle 'position' a pokud patří do skupiny, tak je
     *  tato skupina vnořena jako další array (parametrů). První vnořený index (0) obsahuje samotnou skupinu. Následující
     *  indexy jsou parametry této skupiny.
     *
     * Např. array[
     *   0 => 'parameter_0',
     *   1 => array[
     *       0 => 'parameterGroup',
     *       1 => 'parameter_1',
     *       2 => 'parameter_2'],
     *   2 => 'parameter_3']
     *
     * @param ProductType $productType
     *
     * @return array
     */
    public function getProductTypeParametersInGroupedArray(ProductType $productType): array
    {
        $productTypeParameters = $productType->getProductTypeParameters()->toArray();

        $results = [];
        $parameterGroupIdLast = null;
        /** @var ProductTypeParameter $productTypeParameter */
        foreach ($productTypeParameters as $productTypeParameter) {
            $parameterGroup = $productTypeParameter->getParameterGroup();
            if ($parameterGroup !== null) {
                $parameterGroupId = $parameterGroup->getId();
                if ($parameterGroupId !== $parameterGroupIdLast) {
                    if (!empty($tempArray)) {
                        $results[] = $tempArray;
                    }
                    $tempArray = [];
                }
                $tempArray[] = $productTypeParameter;
                $parameterGroupIdLast = $parameterGroupId;
            } else {
                if (!empty($tempArray)) {
                    $results[] = $tempArray;
                    $tempArray = [];
                }
                $results[] = $productTypeParameter;
                $parameterGroupIdLast = null;
            }
        }
        if (!empty($tempArray)) {
            $results[] = $tempArray;
        }

        return $results;
    }

    /**
     * Obecná metoda, která aktualizuje hodnotu kterékoli boolean property.
     *  Volá dynamicky konkrétní 'set'+'$paremeter' metodu.
     *
     * @param string $parameter Název property (required, filter, collapsed, displayNegativeValue)
     * @param $productTypeParameterId
     * @param string $checked Jestli je checkbox zaškrtnut ("true"/"false")
     *
     * @throws ProductTypeParameterNotFound
     */
    public function updateProductTypeParameterCheckbox(string $parameter, $productTypeParameterId, string $checked)
    {
        /** @var ProductTypeParameter $parameter */
        $productTypeParameter = $this->productTypeParameterRepository->find($productTypeParameterId);
        if ($productTypeParameter !== null) {
            $methodName = 'set' . ucfirst($parameter);
            $productTypeParameter->$methodName($checked);
            $this->em->flush();
        } else {
            throw new ProductTypeParameterNotFound('ProcutTypeParameter id ' . $productTypeParameterId . ' not found');
        }
    }

    /**
     * @param string $parameter Název property (required, filter, collapsed, displayNegativeValue)
     * @param $productTypeParameterId
     * @param string $checked Jestli je checkbox zaškrtnut ("true"/"false")
     *
     * @throws \Exception
     *
     * @return bool
     */
    public function updateCheckedProductTypeParameter(string $parameter, $productTypeParameterId, string $checked): bool
    {
        try {
            $this->em->getConnection()->beginTransaction();

            $this->checkedProductTypeParameterConditions($parameter, $productTypeParameterId, $checked);

            $this->em->getConnection()->commit();
            $result = true;
        } catch (\Exception $e) {
            $this->em->getConnection()->rollBack();
            throw $e;
        }

        return $result;
    }

    /**
     * Stará se o logiku, kdy např. při frontendovém odškrtnutí required se musí rovnou odškrtnout i filer a collapsed apod.
     *   O tuto logiku se stará jak javascript (vizuelně) tak tady tento backend.
     *
     * @param string $parameter
     * @param $productTypeParameterId
     * @param string $checked
     */
    private function checkedProductTypeParameterConditions(string $parameter, $productTypeParameterId, string $checked)
    {
        switch ($parameter) {
            case 'required':
                if ($checked === 'false') {
                    $this->updateProductTypeParameterCheckbox('filter', $productTypeParameterId, 'false');
                    $this->updateProductTypeParameterCheckbox('collapsed', $productTypeParameterId, 'false');
                }
                break;

            case 'filter':
                if ($checked === 'false') {
                    $this->updateProductTypeParameterCheckbox('collapsed', $productTypeParameterId, 'false');
                }
                break;
        }
        $this->updateProductTypeParameterCheckbox($parameter, $productTypeParameterId, $checked);
    }
}
