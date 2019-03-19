<?php

namespace App\Controller;

use App\Entity\Job;
use App\Form\JobFormType;
use App\Repository\JobRepository;
use Doctrine\ORM\EntityManagerInterface;
use Goutte\Client;
use Knp\Component\Pager\PaginatorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class JobController extends AbstractController
{
    private $countCheckJobs = 0;
    private $countAddJobs = 0;
    private $JobRows = [];
    private $jobIds = [];

    /**
    * @Route("/", name="job_list")
     */
    public function jobList(Request $request, PaginatorInterface $paginator, LoggerInterface $logger, JobRepository $repository)
    {


        $queryBuilder = $repository->getWithSearchQueryBuilder($request);

        $pagination = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page',1),
            50
        );

        $jobListFilterLocations = Job::JOB_LIST_FILTER_LOCATION;
        $jobListFilterLocations[] = 'etc';

        return $this->render('job/index.html.twig', [
            'jobs' => $pagination,
            'jobListFilterLocations' => $jobListFilterLocations,
        ]);
    }


    /**
     * @Route("/register", name="register_job")
     */
    public function jobRegister(Request $request, EntityManagerInterface $em)
    {

        $form = $this->createForm(JobFormType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $job = $form->getData();

//            $job

            $em->persist($job);
            $em->flush();

            $this->addFlash('success', 'Job data Created!');
            return $this->redirectToRoute('job_list');

        }


        return $this->render('job/register.html.twig', [
            'articleForm' => $form->createView()
        ]);
    }



    /**
     * @Route("/collect/linkedin", name="get_linkedin_job_data")
     */
    public function getLinkedinJobData(Request $request, EntityManagerInterface $em, JobRepository $repository, Client $client, LoggerInterface $logger)
    {

        $linkBase = $request->query->get('jobListLink');

//        $linkBase = 'https://www.linkedin.com/jobs/search/?currentJobId=1109843956&distance=100&f_E=2&f_JT=F&f_TP=1%2C2%2C3%2C4&keywords=php&location=Berlin%2C%20Berlin%2C%20Germany&locationId=PLACES.de.2-1';

        if ($linkBase) {

            $hmltDatas = $client->request(
                'GET',
                $linkBase,
                [],
                [],
                [
                    'Host' => 'www.linkedin.com',
                    'User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:50.0) Gecko/20100101 Firefox/50.0',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'Connection' => 'keep-alive',
                    'Upgrade-Insecure-Requests' => '1',
                ]
            );


            if ($hmltDatas->filter('.results-context-header__text')->count()) {


                $getText = $hmltDatas->filter('.results-context-header__text')->text();
//        <p class="results-context-header__text">1 - 25 of <span class="results-context-header__job-total">527 jobs</span></p>

                $pattern = "/[0-9]+/";
                preg_match_all($pattern,$getText,$getNumber);
                $totalPage = $getNumber[0][1];


                for ($i = 0; $i <= $totalPage-1; $i++) {

                    $pageCount = $i*25;
                    // Linkedin Joblist page
                    $link = $linkBase.'&start='.$pageCount;

                    $hmltDatas = $client->request(
                        'GET',
                        $link,
                        [],
                        [],
                        [
                            'Host' => 'www.linkedin.com',
                            'User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:50.0) Gecko/20100101 Firefox/50.0',
                            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                            'Accept-Language' => 'en-US,en;q=0.5',
                            'Accept-Encoding' => 'gzip, deflate, br',
                            'Connection' => 'keep-alive',
                            'Upgrade-Insecure-Requests' => '1',
                        ]
                    );

                    $hmltDatas->filter('.jobs-search-result-item')
                              ->each(function ($node) {

                                  $this->countCheckJobs++;

                                  $this->JobRows[] = $node;

                              });

                }

                foreach ($this->JobRows as $nodeRow) {

                    if ($this->checkDuplicationFromNode($nodeRow, $repository)) {
                        $this->addNewJobDataFromNode($nodeRow, $em, Job::SOURCE_LINKEDIN);
                        $this->countAddJobs++;
                    }

                }


                $logger->info('jobid', $this->jobIds);

//                dd($this->jobIds);

                $this->addFlash(
                    'notice',
                    'add job : '.$this->countAddJobs.' / Checked Job : '.$this->countCheckJobs
                );

//                return $this->redirectToRoute('job_list');
                return $this->render('job/gettingJob.html.twig', [

                ]);


            }else{

                $this->addFlash(
                    'notice',
                    'linke ('.$linkBase.') is invalid'
                );

                return $this->render('job/gettingJob.html.twig', [

                ]);

            }



        }else{

            return $this->render('job/gettingJob.html.twig', [

            ]);

        }


    }


    /**
     * @Route("/changeApplicationState/{jobId}", name="change_application_state")
     */
    public function changeApplicationState(Request $request, Job $job, EntityManagerInterface $em)
    {

        $state = $request->query->get('state');
        $etcValue = $request->query->get('etcValue');

        if ($state) {
            $job->setApplyState($state);

            if ($state === 'trying') {
                $job->setApplyAt(new \DateTime(Date('Y-m-d H:i:s')));
            }
        }


        if ($etcValue) {
            $job->setEtc($etcValue);
        }

        $em->persist($job);
        $em->flush();

        return new JsonResponse([
            'result' => 'success',
            'resultMent' => 'complete Updating ('.$job->getCompany().')',
            'resultId' => $job->getId()
            ]);
    }


    /**
     * @Route("/row_linkedin", name="collect_linkedin_rowdata")
     */
    public function collectLinkedinRowdata(Request $request,Client $client)
    {

//        $link = $request->query->get('jobListLink');
//        $link = 'https://www.linkedin.com/jobs/search/?f_C=4975264&f_TP=1%2C2%2C3%2C4&keywords=&location=Worldwide&locationId=OTHERS.worldwide';

        $links = [];


        $linkBase = 'https://www.linkedin.com/jobs/search/?f_E=2%2C4&f_GC=de.3-2-161&f_JT=F%2CC&f_TP=1%2C2%2C3%2C4&keywords=php&location=Germany&locationId=de%3A0';



        $hmltDatas = $client->request(
            'GET',
            $linkBase,
            [],
            [],
            [
                'Host' => 'www.linkedin.com',
                'User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:50.0) Gecko/20100101 Firefox/50.0',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Connection' => 'keep-alive',
                'Upgrade-Insecure-Requests' => '1',
            ]
        );

        $hmltDatas->filter('.results-context-header__text');

        $getText = $hmltDatas->filter('.results-context-header__text')->text();
//        <p class="results-context-header__text">1 - 25 of <span class="results-context-header__job-total">527 jobs</span></p>

        $pattern = "/[0-9]+/";
        preg_match_all($pattern,$getText,$getNumber);
        $totalPage = $getNumber[0][1];



        if ($hmltDatas->filter('.results-context-header__text')->count()) {


            $getText = $hmltDatas->filter('.results-context-header__text')->text();
//        <p class="results-context-header__text">1 - 25 of <span class="results-context-header__job-total">527 jobs</span></p>

            $pattern = "/[0-9]+/";
            preg_match_all($pattern,$getText,$getNumber);
            $totalPage = $getNumber[0][1];


            for ($i = 0; $i <= $totalPage-1; $i++) {

                $pageCount = $i*25;
                // Linkedin Joblist page
                $link = $linkBase.'&start='.$pageCount;

                $links[] = $link;

            }


        }


        dd($links);



        return $this->render('job/index.html.twig', [
            'controller_name' => 'CollectLinkedinController',
        ]);
    }





    /**
     * @Route("/statistic/applyjob", name="statistic_applyjob")
     */
    public function statisticApplyJob(JobRepository $repository, PaginatorInterface $paginator, Request $request)
    {
        $queryBuilder = $repository->getStatisticApplyJob()
            ->getQuery()
            ->getResult()
            ;

        $pagination = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page',1),
            50
        );

        return $this->render('job/statistic.html.twig', [
            'jobs' => $pagination
        ]);
    }
    

    public function addNewJobDataFromNode(Crawler $node, EntityManagerInterface $em, string $source){

        $job = new Job();
        $job->setLink($node->filter('.listed-job-posting--is-link')->attr('href'));
        $job->setJobId($node->filter('.listed-job-posting--is-link')->attr('data-job-id'));
        $job->setTitle($node->filter('.listed-job-posting__title')->text());
        $job->setCompany($node->filter('.listed-job-posting__company')->text());
        $job->setLocation($node->filter('.listed-job-posting__location')->text());
        $job->setDescription($node->filter('.listed-job-posting__description')->text());
        $job->setpublishedatAfterCheckAgo($node->filter('.posted-time-ago__text')->text());
        $job->setSource($source);

        $em->persist($job);
        $em->flush();

    }


    public function checkDuplicationFromNode(Crawler $node, JobRepository $JobRepository)
    {
        $jobId = $node->filter('.listed-job-posting--is-link')->attr('data-job-id');
        $job = $JobRepository->findOneBy(['jobId' => $jobId]);

        if ($job) {
//            $this->jobIds[] = $jobId;
            $this->jobIds[] = $jobId . "/" . $node->filter('.listed-job-posting__location')->text(
                ) . "/" . $node->filter('.listed-job-posting__company')->text(
                ) . "/" . $node->filter('.listed-job-posting__title')->text();

            return false;
        }else{
            return true;
        }
    }


    public function checkJobTitleToIncludeWord(Crawler $node, string $word): bool
    {
        $jobTitle = strtolower($node->filter('.listed-job-posting__title')->text());
        if (strpos($jobTitle,$word)) {
            return true;
        }else{
            return false;
        }
    }

    
   
}
