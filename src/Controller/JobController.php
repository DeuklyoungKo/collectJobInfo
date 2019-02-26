<?php

namespace App\Controller;

use App\Entity\Job;
use Goutte\Client;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class JobController extends AbstractController
{

    /**
     * @var Client
     */
    private $client;

    private $countCheckJobs = 0;

    private $countAddJobs = 0;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
    * @Route("/", name="job_list")
     */
    public function jobList(Request $request, PaginatorInterface $paginator)
    {

        $jobRepository = $this->getDoctrine()->getRepository(Job::class);

        $q = $request->query->get('q');
        $stateFilter = $request->query->get('stateFilter');
        $language = $request->query->get('language');

        $queryBuilder = $jobRepository->getWithSearchQueryBuilder($q,$language,$stateFilter);

        $rowQuery =  $queryBuilder->getQuery();

        $pagination = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page',1),
            50
        );

        return $this->render('job/index.html.twig', [
            'jobs' => $pagination,
        ]);
    }



    /**
     * @Route("/collect/linkedin", name="get_linkedin_job_data")
     */
    public function getLinkedinJobData(Request $request)
    {

        $link = $request->query->get('jobListLink');

//        $link = 'https://www.linkedin.com/jobs/search/?currentJobId=1109843956&distance=100&f_E=2&f_JT=F&f_TP=1%2C2%2C3%2C4&keywords=php&location=Berlin%2C%20Berlin%2C%20Germany&locationId=PLACES.de.2-1';

        if ($link) {

            $hmltDatas = $this->client->request(
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



            if ($hmltDatas->filter('.results-context-header__text')->count()) {

                $getText = $hmltDatas->filter('.results-context-header__text')->text();
//        <p class="results-context-header__text">1 - 25 of <span class="results-context-header__job-total">527 jobs</span></p>

                $pattern = "/[0-9]+/";
                preg_match_all($pattern,$getText,$getNumber);
                $totalPage = $getNumber[0][1];


                for ($i = 0; $i <= $totalPage-1; $i++) {

                    $pageCount = $i*25;
                    // Linkedin Joblist page
                    $link .= '&start='.$pageCount;

                    $hmltDatas = $this->client->request(
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

                                  $em = $this->getDoctrine()->getManager();
                                  $JobRepositoryIn = $this->getDoctrine()->getRepository(Job::class);

                                  $jobId = $node->filter('.listed-job-posting--is-link')->attr('data-job-id');

                                  $job = $JobRepositoryIn->findOneBy(['jobId' => $jobId]);

                                  if ($job) {
                                      return;
                                  }else{
                                      $this->countAddJobs++;
                                  }

                                  $job = new Job();
                                  $job->setLink($node->filter('.listed-job-posting--is-link')->attr('href'));
                                  $job->setJobId($jobId);
                                  $job->setTitle($node->filter('.listed-job-posting__title')->text());
                                  $job->setCompany($node->filter('.listed-job-posting__company')->text());
                                  $job->setLocation($node->filter('.listed-job-posting__location')->text());
                                  $job->setDescription($node->filter('.listed-job-posting__description')->text());
                                  $job->setpublishedatAfterCheckAgo($node->filter('.posted-time-ago__text')->text());

                                  $em->persist($job);
                                  $em->flush();
                              });


                }


                $this->addFlash(
                    'notice',
                    'add job : '.$this->countAddJobs.' / Checked Job : '.$this->countCheckJobs
                );

                return $this->redirectToRoute('job_list');

            }else{

                $this->addFlash(
                    'notice',
                    'linke ('.$link.') is invalid'
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
    public function changeApplicationState(Request $request, Job $job)
    {

        $state = $request->query->get('state');
        $etcValue = $request->query->get('etcValue');

        $em = $this->getDoctrine()->getManager();

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
     * @Route("/row", name="collect_linkedin_rowdata")
     */
    public function collectLinkedinRowdata()
    {


        $link = 'https://www.linkedin.com/jobs/search/?currentJobId=1109843956&distance=100&f_E=2&f_JT=F&f_TP=1%2C2%2C3%2C4&keywords=php&location=Berlin%2C%20Berlin%2C%20Germany&locationId=PLACES.de.2-1';

        $hmltDatas = $this->client->request(
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

        dd($hmltDatas);

        $JobRepository = $this->getDoctrine()->getRepository(Job::class);

        return $this->render('job/index.html.twig', [
            'controller_name' => 'CollectLinkedinController',
        ]);
    }

}
