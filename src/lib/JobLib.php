<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 2019-03-13
 * Time: 오후 8:54
 */

namespace App\lib;


use App\Entity\Job;

class JobLib
{

    public function makeLocationArray():array
    {
        $locationArray = [];

        $locationArray['All'] = 'All';

        foreach (Job::JOB_LIST_FILTER_LOCATION as $city) {
            $locationArray[$city] = $city;
        }

        $locationArray['etc'] = 'etc';

        return $locationArray;

    }


    public function makeJobApplyStateArray():array
    {
        $JobApplyStateArray = [];

        foreach (Job::JOB_APPLY_STATE as $state) {
            $JobApplyStateArray[$state] = $state;
        }

        return $JobApplyStateArray;

    }
}