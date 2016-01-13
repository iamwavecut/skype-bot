<?php
namespace Bot\Plugins;

use Bot\Filebase\Factory;
use Bot\Plugins\Infrastructure\Timer;
use DI\Annotation\Inject;
use GuzzleHttp\RequestOptions;

class JenkinsWatcher extends Timer
{
    const PERIOD = 60;
    const CHATGROUP = 'jenkins';

    /**
     * @Inject
     * @var Admin
     */
    private $admin;

    public function init()
    {
        Admin::registerGroup(self::CHATGROUP);

        $this->ev->on('tick', [$this, 'processJobs']);
    }

    public function processJobs($time)
    {
        if ($time >= $this->currentTime + self::PERIOD) {
            $this->currentTime = $time;

            $builds = [];

            // TODO move to config
            $jobsUrl = "http://jenkinshost/api/json?tree=jobs[name,"
                       . "lastBuild[building,timestamp,actions[parameters[name,value]],fullDisplayName,result,number,url,"
                       . 'changeSet[items[msg,author[fullName]]]'
                       . "]]";
//            \Util::debug($jobsUrl);
            $response = $this->http->getResponse('GET', $jobsUrl);
            if ($response && $response->getStatusCode() === 200 && $body = $response->getBody()) {

                /** @var array $buildsRaw */
                $buildsRaw = json_decode($body, true)['jobs'];
                $jobs = $this->pluginConfig['jobs'];
                foreach ($buildsRaw as $build) {
                    if (in_array($build['name'], array_keys($jobs))) {
                        $builds[$build['name']] = [];

                        if (isset($build['lastBuild']['actions'][0]['parameters'])) {
                            foreach ($build['lastBuild']['actions'][0]['parameters'] as $param) {
                                $builds[$build['name']][$param['name']] = $param['value'];
                            }
                        }

                        $builds[$build['name']]['result'] = $build['lastBuild']['result'];
                        $builds[$build['name']]['displayName'] = $build['lastBuild']['fullDisplayName'];
                        $builds[$build['name']]['number'] = $build['lastBuild']['number'];
                        $builds[$build['name']]['url'] = $build['lastBuild']['url'];
                        $builds[$build['name']]['time'] = $build['lastBuild']['timestamp'];
                        $builds[$build['name']]['building'] = $build['lastBuild']['building'];

                        $changes = [];
                        if (!empty($build['lastBuild']['changeSet']['items'])) {
                            foreach ($build['lastBuild']['changeSet']['items'] as $change) {
                                $changes[] = [$change['author']['fullName'], $change['msg']];
                            }
                        }
                        $builds[$build['name']]['changes'] = $changes;
                    }
                }

                $jobsDb = Factory::create(\Util::getLocalPath() . DS . 'jobs.json');

                $msg = '';
                $prevBuilds = $jobsDb['jobs'] ?: [];
                foreach ($builds as $name => $build) {
                    $extra = [];
                    $job = !empty($jobs[$name]) ? $jobs[$name] : [];

                    if (
                        isset($prevBuilds[$name])
                        && (
                            $build['number'] !== $prevBuilds[$name]['number']
                            || $build['result'] !== $prevBuilds[$name]['result']
                        )
                    ) {
                        $xtext = '';
                        if (isset($build['DEPLOY_STAGE'])) {
                            $extra['on'] = $build['DEPLOY_STAGE'];
                        }
                        if (isset($build['TARGET_SERVER'])) {
                            $extra['on'] = $build['TARGET_SERVER'];
                        }
                        if (isset($build['DEPLOY_BRANCH'])) {
                            $extra['source:'] = $build['DEPLOY_BRANCH'];
                        }

                        if (count($extra)) {
                            $texts = [];
                            foreach ($extra as $xname => $xval) {
                                $texts[] = "$xname $xval";
                            }
                            $xtext = implode(', ', $texts);
                        }

                        if (!$build['building']) {
                            \Util::console("Jenkins: job '{$name}'' state {$build['result']}");

                            $result = $build['result'];
                            $link = '';
                            $versionUrl = null;
                            switch ($result) {
                                case 'SUCCESS':
                                    if (array_key_exists('skipSections', $job)
                                        && in_array(
                                            'success',
                                            $job['skipSections']
                                        )
                                    ) {
                                        \Util::console("Jenkins: job '{$name}' skip success");
                                        continue 2;
                                    }
                                    $result = '(y) SUCCESS';
                                    break;
                                case 'FAILURE':
                                    if (array_key_exists('skipSections', $job)
                                        && in_array(
                                            'failure',
                                            $job['skipSections']
                                        )
                                    ) {
                                        \Util::console("Jenkins: job '{$name}' skip failure");
                                        continue 2;
                                    }
                                    $result = ';( FAILURE';
                                    $link = $build['url'];
                                    break;
                            }

                            if (!empty($job)) {
                                if (array_values($job) === $job) {
                                    $versionUrl = current($job);
                                } else {
                                    if (
                                        array_key_exists('DEPLOY_STAGE', $build)
                                        && !empty($build['DEPLOY_STAGE'])
                                        && !empty($job[$build['DEPLOY_STAGE']])
                                    ) {
                                        $versionUrl = $job[$build['DEPLOY_STAGE']];
                                    } elseif (
                                        array_key_exists('TARGET_SERVER', $build)
                                        && !empty($build['TARGET_SERVER'])
                                        && !empty($job[$build['TARGET_SERVER']])
                                    ) {
                                        $versionUrl = $job[$build['TARGET_SERVER']];
                                    }
                                }
                            }

                            $version = null;
                            if ($versionUrl && $build['result'] === 'SUCCESS') {
                                $response = $this->http->getResponse(
                                    'GET',
                                    $versionUrl,
                                    [
                                        RequestOptions::AUTH => [
                                            $this->pluginConfig['auth']['user'],
                                            $this->pluginConfig['auth']['passwd'],
                                        ],
                                    ]
                                );
                                if ($response && $response->getStatusCode() === 200 && $body = $response->getBody()) {
                                    $xtext .= ' (ver. ' . trim($body) . ')';
                                }
                            }

                            $msg .= PHP_EOL . "[J] {$build['displayName']} {$result} {$xtext} "
                                    . date('Y-m-d H:i', $build['time'] / 1000) . " {$link}" . PHP_EOL;

                            if (count($build['changes'])) {
                                $msg .= 'Changeset:' . PHP_EOL;
                                $changeSet = [];
                                foreach ($build['changes'] as $change) {
                                    list($changeName, $changeMessage) = $change;
                                    $changeName = trim($changeName);
                                    if (!isset($changeSet[$changeName])) {
                                        $changeSet[$changeName] = [];
                                    }
                                    $taskNum = preg_replace('/^([A-Z]+\-\d+).*$/', '\1', $changeMessage);
                                    if ($taskNum) {
                                        if (!isset($changeSet[$changeName][$taskNum])) {
                                            $changeSet[$changeName][$taskNum] = 0;
                                        }
                                        $changeSet[$changeName][$taskNum]++;
                                    }
                                }

                                foreach ($changeSet as $changer => $tasks) {
                                    foreach ($tasks as $task => $count) {
                                        $msg .= $changer . ': ' . $task . " ({$count} commit(s))" .
                                                PHP_EOL;
                                    }
                                }
                            }
                        } else {
                            \Util::console("Jenkins: job '{$name}' started");

                            if (!empty($job)
                                && array_key_exists('skipSections', $job)
                                && in_array('start', $job['skipSections'])
                            ) {
                                \Util::console("Jenkins: job '{$name}' skip start");
                                continue;
                            }
                            $msg .= PHP_EOL . "[J] {$build['displayName']} build began {$xtext}";
                        }
                    }
                }
                $jobsDb['jobs'] = $builds;
                if ($msg) {
                    $chats = $this->db['chats'] ?: [];
                    if (isset($chats[self::CHATGROUP]) && count($chats[self::CHATGROUP])) {
                        foreach ($chats[self::CHATGROUP] as $chatName) {
                            $this->core->send($chatName, trim($msg));
                        }
                    }
                }
            } else {
                \Util::console('Jenkins builds failed to fetch: ' . ($response ? $response->getBody() : '.'));
            }
        }
    }
}
