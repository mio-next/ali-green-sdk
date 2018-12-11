<?php
namespace NetCasts\AliGreen;

use Green\Request\V20170112\TextScanRequest;
use Green\Request\V20170112\ImageAsyncScanRequest;

include_once __DIR__ . '/../aliyun/aliyun-php-sdk-core/Config.php';

class Green
{
    const
        SUCCESS = 200,
        TASK_SUCCESS = 200,
        TASK_FAIL = 500,

        END = 0;

    /**
     * @var string
     */
    private $region;

    /**
     * @var
     */
    private $accessKey;

    /**
     * @var
     */
    private $secretKey;

    /**
     * Green constructor.
     * @param $accessKey
     * @param $secretKey
     * @param string $region
     */
    public function __construct($accessKey, $secretKey, $region = 'cn-shanghai')
    {
        $this->region       = $region;
        $this->accessKey    = $accessKey;
        $this->secretKey    = $secretKey;
    }

    /**
     * @return \DefaultAcsClient
     */
    private function client()
    {
        $clientProfile = \DefaultProfile::getProfile($this->region, $this->accessKey, $this->secretKey);
        \DefaultProfile::addEndpoint(
            $this->region, $this->region, 'Green', 'green.' . $this->region . '.aliyuncs.com'
        );

        return new \DefaultAcsClient($clientProfile);
    }

    /**
     * @param $images
     * @return array|bool
     */
    public function image($images)
    {
        if (empty($images)) {
            return false;
        }

        $request = new ImageAsyncScanRequest();
        $request->setMethod('POST');
        $request->setAcceptFormat('JSON');

        if (is_array($images)) {
            $tasks = array();

            foreach ($images as $index => $image) {
                array_push($tasks, array(
                    'url' => $image,
                    'time' => round(microtime(true) * 1000),
                    'dataId' => md5(uniqid('task' . $index)),
                ));
            }

            $request->setContent(
                json_encode(array(
                    'tasks' => $tasks, 'scenes' => array('ad', 'porn', 'terrorism')
                ))
            );
        }

        else {
            $tasks = array(array('taskId' => md5(uniqid(true)), 'url' => $images));
            $request->setContent(
                json_encode(array(
                    'tasks' => $tasks, 'scenes' => array('ad', 'porn', 'terrorism')
                ))
            );
        }

        return $this->getResponse($request, true);
    }

    /**
     * @param $contents
     * @return array|bool
     */
    public function text($contents)
    {
        if (empty($contents)) {
            return false;
        }

        $request = new TextScanRequest();
        $request->setMethod('POST');
        $request->setAcceptFormat('JSON');

        if (is_array($contents)) {
            $tasks = array();

            foreach ($contents as $index => $content) {
                array_push($tasks, array(
                    'time'      => round(microtime(true) * 1000),
                    'dataId'    => md5(uniqid('task' . $index)),
                    'content'   => $content,
                    'category'  => 'post',
                ));
            }

            $request->setContent(
                json_encode(array('tasks' => $tasks, 'scenes' => array('antispam')))
            );
        }

        else {
            $tasks = array(array('taskId' => md5(uniqid(true)), 'content' => $contents));

            $request->setContent(
                json_encode(array('tasks' => $tasks, 'scenes' => array('antispam')))
            );
        }

        return $this->getResponse($request);
    }

    /**
     * @param \RoaAcsRequest $request
     * @param bool $image
     * @return array|bool
     */
    private function getResponse(\RoaAcsRequest $request, $image = false)
    {
        $client = $this->client();
        $result = array();

        try {
            $response = $client->getAcsResponse($request);

            if ($response->code != self::SUCCESS) {
                return false;
            }

            foreach ($response->data as $task) {
                $result[] = self::SUCCESS == $task->code
                    ? $this->resolveScene($task, $image)
                    : array('task_id' => $task->taskId, 'code' => self::TASK_FAIL, 'msg' => 'Fail', 'content' => $task->content);
            }

            return $result;
        }

        catch (Exception $e) {
            return false;
        }
    }

    /**
     * @param $task
     * @param bool $image
     * @return array
     */
    private function resolveScene($task, $image = false)
    {
        $flag = true;
        $return = array();

        if ($image) {
            $flag = $task->code == self::SUCCESS;
        }

        else {
            foreach ($task->results as $result) {
                $suggestion = $result->suggestion;
                $rate = $result->rate;
                if($suggestion != 'pass' && $rate > 80){
                    $flag = false;
                    break;
                }
            }
        }

        if ($flag) {
            $return = array(
                'task_id' => $task->taskId, 'code' => self::SUCCESS, 'msg' => $task->msg,
                $image ? 'url' : 'content' => $task->{$image ? 'url' : 'content'}
            );
        }
        else {
            $return = array(
                'task_id' => $task->taskId, 'code' => self::TASK_FAIL, 'msg' => 'Fail',
                $image ? 'url' : 'content' => $task->{$image ? 'url' : 'content'}
            );
        }

        return $return;
    }
}