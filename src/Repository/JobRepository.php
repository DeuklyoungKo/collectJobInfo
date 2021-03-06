<?php

namespace App\Repository;

use App\Entity\Job;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Job|null find($id, $lockMode = null, $lockVersion = null)
 * @method Job|null findOneBy(array $criteria, array $orderBy = null)
 * @method Job[]    findAll()
 * @method Job[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class JobRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Job::class);
    }


    public function checkDoubleData(int $jobId)
    {
        $qb = $this->createQueryBuilder('j')
                   ->andWhere('j.job_id = :jobId')
                   ->setParameter('jobId', $jobId)
                   ->getQuery();

        return $qb->execute();
    }


    public function getWithSearchQueryBuilder(?string $term, ?string $language, ?string $stateFilter): QueryBuilder
    {
        $qb = $this->createQueryBuilder('j')
            ->orderBy('j.publishedAt')
        ;

        if ($stateFilter) {
            $qb->andWhere("j.applyState = :stateFilter")
               ->setParameter('stateFilter', $stateFilter)
            ;
        }

        if ($term) {
            $qb->andWhere("j.company like :term")
                ->setParameter('term', "%".$term."%")
            ;
        }

        if ($language || $language !== 'ALL') {

            if ($language === 'English') {
                $qb->andWhere("REGEXP(j.description, '[[:blank:]]on[[:blank:]]|[[:blank:]]and[[:blank:]]|[[:blank:]]the[[:blank:]]') = 1");
            }

            if ($language === 'German') {
                $qb->andWhere("REGEXP(j.description, '[[:blank:]]on[[:blank:]]|[[:blank:]]and[[:blank:]]|[[:blank:]]the[[:blank:]]') = 0");
            }

        }

        return $qb->orderBy('j.publishedAt','ASC');
    }
}
