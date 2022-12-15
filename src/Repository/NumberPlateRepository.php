<?php

namespace App\Repository;

use App\Entity\NumberPlate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NumberPlate>
 *
 * @method NumberPlate|null find($id, $lockMode = null, $lockVersion = null)
 * @method NumberPlate|null findOneBy(array $criteria, array $orderBy = null)
 * @method NumberPlate[]    findAll()
 * @method NumberPlate[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NumberPlateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NumberPlate::class);
    }

    public function save(NumberPlate $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(NumberPlate $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findWithinTime(string $numberPlate, \DateTime $dateTime): int
    {
        $qb = $this->createQueryBuilder('np')
            ->select('count(np.id)')
            ->andWhere('np.numberPlate = :numberPlate')
            ->andWhere('np.createdAt >= :dateTime')
            ->setParameter('numberPlate', $numberPlate)
            ->setParameter('dateTime', $dateTime)
        ;

        return $qb->getQuery()->getSingleScalarResult();
    }
}
