<?php

namespace Klsoft\Yii3DataReaderDoctrine;

use InvalidArgumentException;
use Traversable;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Yiisoft\Data\Reader\DataReaderInterface;
use Yiisoft\Data\Reader\Filter\AndX;
use Yiisoft\Data\Reader\Filter\LikeMode;
use Yiisoft\Data\Reader\Filter\OrX;
use Yiisoft\Data\Reader\Filter\All;
use Yiisoft\Data\Reader\Filter\Between;
use Yiisoft\Data\Reader\Filter\Equals;
use Klsoft\Yii3DataReaderDoctrine\Filter\ObjectEquals;
use Yiisoft\Data\Reader\Filter\EqualsNull;
use Yiisoft\Data\Reader\Filter\GreaterThan;
use Yiisoft\Data\Reader\Filter\GreaterThanOrEqual;
use Yiisoft\Data\Reader\Filter\In;
use Yiisoft\Data\Reader\Filter\LessThan;
use Yiisoft\Data\Reader\Filter\LessThanOrEqual;
use Yiisoft\Data\Reader\Filter\Like;
use Yiisoft\Data\Reader\Filter\Not;
use Yiisoft\Data\Reader\FilterInterface;
use Yiisoft\Data\Reader\Sort;

/**
 * @inheritDoc
 */
final class DoctrineDataReader implements DataReaderInterface
{
    private readonly ClassMetadata $entityNetadata;
    private FilterInterface $filter;
    private ?int $limit = null;
    private int $offset = 0;
    private ?Sort $sort = null;

    /**
     * @param EntityManagerInterface $entityManager The EntityManager instance.
     * @param string $entityClass The Entity class name.
     * @param array $fields The Entity fields. Optional.
     * If completed, the read() method will return an array of fields.
     * Otherwise, it will return an array containing the entity itself.
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private string                 $entityClass,
        private array                  $fields = [])
    {
        $this->entityNetadata = $this->entityManager->getClassMetadata($this->entityClass);
        $this->filter = new All();
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): Traversable
    {
        yield from $this->read();
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $identifierFields = $this->entityNetadata->getIdentifier();
        return $this->buildWhere(
            $this->filter,
            0,
            $qb
                ->select($qb->expr()->count(count($identifierFields) === 1 ? 'entity.' . $identifierFields[0] : 'entity'))
                ->from($this->entityClass, 'entity'))
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @inheritDoc
     */
    public function withFilter(FilterInterface $filter): static
    {
        $new = clone $this;
        $new->filter = $filter;
        return $new;
    }

    /**
     * @inheritDoc
     */
    public function getFilter(): FilterInterface
    {
        return $this->filter;
    }

    /**
     * @inheritDoc
     */
    public function withLimit(?int $limit): static
    {
        if ($limit < 0) {
            throw new InvalidArgumentException('The limit must not be less than 0.');
        }

        $new = clone $this;
        $new->limit = $limit;
        return $new;
    }

    /**
     * @inheritDoc
     */
    public function getLimit(): ?int
    {
        return $this->limit;
    }

    /**
     * @inheritDoc
     */
    public function withOffset(int $offset): static
    {
        $new = clone $this;
        $new->offset = $offset;
        return $new;
    }

    /**
     * @inheritDoc
     */
    public function getOffset(): int
    {
        return $this->offset;
    }

    /**
     * @inheritDoc
     */
    public function read(): iterable
    {
        $qb = $this->createQueryBuilderSelectFields();
        $qb->setFirstResult($this->offset);
        if ($this->limit !== null) {
            $qb->setMaxResults($this->limit);
        }
        if ($this->sort !== null) {
            $criteria = $this->sort->getCriteria();
            foreach ($criteria as $field => $order) {
                $qb = $qb->addOrderBy("entity.$field", $order === SORT_DESC ? 'DESC' : 'ASC');
            }
        }
        return $qb
            ->getQuery()
            ->getResult();
    }

    private function createQueryBuilderSelectFields()
    {
        return $this->buildWhere(
            $this->filter,
            0,
            $this->entityManager->createQueryBuilder()
                ->select(empty($this->fields) ? 'entity' : array_map(fn($field) => "entity.$field", $this->fields))
                ->from($this->entityClass, 'entity'));
    }

    private function buildWhere(FilterInterface $filter, int $minFilterIndex, QueryBuilder $qb): QueryBuilder
    {
        if ($filter instanceof AndX ||
            $filter instanceof OrX) {
            for ($i = 0; $i < count($filter->filters); $i++) {
                $qb = $this->buildWhereAndSetParameter(
                    $filter->filters[$i],
                    $minFilterIndex + $i,
                    $minFilterIndex,
                    $minFilterIndex + count($filter->filters) - 1,
                    $qb,
                    $i > 0 || $minFilterIndex > 0 ? $filter instanceof AndX : null);
            }
        } else {
            $qb = $this->buildWhereAndSetParameter(
                $filter,
                0,
                $minFilterIndex,
                $minFilterIndex,
                $qb);
        }
        return $qb;
    }

    private function buildWhereAndSetParameter(
        FilterInterface $filter,
        int             $filterIndex,
        int             $minFilterIndex,
        int             $maxFilterIndex,
        QueryBuilder    $qb,
        ?bool           $isAndWhere = null): QueryBuilder
    {
        $parameterName = null;
        $parameterValue = null;
        $predicate = null;
        if ($filter instanceof Equals || $filter instanceof ObjectEquals) {
            $parameterName = $filter->field . "_value_" . $filterIndex;
            $parameterValue = $filter->value;
            $predicate = $qb->expr()->eq("entity.$filter->field", ":$parameterName");
        } elseif ($filter instanceof Between) {
            $predicate = $qb->expr()->between("entity.$filter->field", $filter->minValue, $filter->maxValue);
        } elseif ($filter instanceof EqualsNull) {
            $predicate = $qb->expr()->isNull("entity.$filter->field");
        } elseif ($filter instanceof GreaterThan) {
            $parameterName = $filter->field . "_value_" . $filterIndex;
            $parameterValue = $filter->value;
            $predicate = $qb->expr()->gt("entity.$filter->field", ":$parameterName");
        } elseif ($filter instanceof GreaterThanOrEqual) {
            $parameterName = $filter->field . "_value_" . $filterIndex;
            $parameterValue = $filter->value;
            $predicate = $qb->expr()->gte("entity.$filter->field", ":$parameterName");
        } elseif ($filter instanceof In) {
            $parameterName = $filter->field . "_value_" . $filterIndex;
            $parameterValue = $filter->values;
            $predicate = $qb->expr()->In("entity.$filter->field", ":$parameterName");
        } elseif ($filter instanceof LessThan) {
            $parameterName = $filter->field . "_value_" . $filterIndex;
            $parameterValue = $filter->value;
            $predicate = $qb->expr()->lt("entity.$filter->field", ":$parameterName");
        } elseif ($filter instanceof LessThanOrEqual) {
            $parameterName = $filter->field . "_value_" . $filterIndex;
            $parameterValue = $filter->value;
            $predicate = $qb->expr()->lte("entity.$filter->field", ":$parameterName");
        } elseif ($filter instanceof Like) {
            $parameterName = $filter->field . "_value_" . $filterIndex;
            $parameterValue = $this->getLikeParameterValue($filter);
            if ($filter->mode === LikeMode::StartsWith) {
                $parameterValue = "$filter->value%";
            } elseif ($filter->mode === LikeMode::EndsWith) {
                $parameterValue = "%$filter->value";
            }
            $predicate = $qb->expr()->like("entity.$filter->field", ":$parameterName");
        } elseif ($filter instanceof Not) {
            if ($filter->filter instanceof Equals  || $filter instanceof ObjectEquals) {
                $parameterName = $filter->filter->field . "_value_" . $filterIndex;
                $parameterValue = $filter->filter->value;
                $predicate = $qb->expr()->neq("entity.{$filter->filter->field}", ":$parameterName");
            } elseif ($filter->filter instanceof EqualsNull) {
                $predicate = $qb->expr()->isNotNull("entity.{$filter->filter->field}");
            } elseif ($filter->filter instanceof In) {
                $parameterName = $filter->filter->field . "_value_" . $filterIndex;
                $parameterValue = $filter->filter->values;
                $predicate = $qb->expr()->notIn("entity.{$filter->filter->field}", ":$parameterName");
            } elseif ($filter->filter instanceof Like) {
                $parameterName = $filter->filter->field . "_value_" . $filterIndex;
                $parameterValue = $this->getLikeParameterValue($filter->filter);
                $predicate = $qb->expr()->notLike("entity.{$filter->filter->field}", ":$parameterName");
            }
        } elseif ($filter instanceof AndX ||
            $filter instanceof OrX) {
            return $this->buildWhere($filter, $minFilterIndex + $maxFilterIndex + 1, $qb);
        }
        if ($predicate !== null) {
            if ($isAndWhere !== null) {
                if ($isAndWhere) {
                    $qb = $qb->andWhere($predicate);
                } else {
                    $qb = $qb->orWhere($predicate);
                }
            } else {
                $qb = $qb->where($predicate);
            }
        }
        if ($parameterName !== null &&
            $parameterValue !== null) {
            $qb = $qb->setParameter($parameterName, $parameterValue);
        }
        return $qb;
    }

    private function getLikeParameterValue(Like $filter)
    {
        $parameterValue = "%$filter->value%";
        if ($filter->mode === LikeMode::StartsWith) {
            $parameterValue = "$filter->value%";
        } elseif ($filter->mode === LikeMode::EndsWith) {
            $parameterValue = "%$filter->value";
        }
        return $parameterValue;
    }


    /**
     * @inheritDoc
     */
    public function readOne(): array|object|null
    {
        if ($this->limit === 0) {
            return null;
        }

        return $this->createQueryBuilderSelectFields()
            ->setFirstResult($this->offset)
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();
    }

    /**
     * @inheritDoc
     */
    public function withSort(?Sort $sort): static
    {
        $new = clone $this;
        $new->sort = $sort;
        return $new;
    }

    /**
     * @inheritDoc
     */
    public function getSort(): ?Sort
    {
        return $this->sort;
    }
}
