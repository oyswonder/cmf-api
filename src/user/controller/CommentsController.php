<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2019 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: wuwu <15093565100@163.com>
// +----------------------------------------------------------------------
namespace api\user\controller;

use api\user\model\CommentModel;
use api\user\model\UserModel;
use api\user\service\CommentService;
use cmf\controller\RestBaseController;

class CommentsController extends RestBaseController
{

    /**
     * 获取用户评论
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getUserComments()
    {
        $param            = $this->request->param();
        $param['user_id'] = $this->getUserId();
        $commentService   = new CommentService();
        $data             = $commentService->userComments($param);

        if (empty($this->apiVersion) || $this->apiVersion == '1.0.0') {
            $response = [$data];
        } else {
            $response = ['list' => $data];
        }
        if ($data->isEmpty()) {
            $this->error('No comment!');
        }
        $this->success('Request successful', $response);

    }

    /**
     * 获取评论
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getComments()
    {
        $param = $this->request->param();
        if (empty($param['object_id'])) {
            $this->error('Parameter object_id dose not exist!');
        }
        if (empty($param['table_name'])) {
            $this->error('Parameter table_name dose not exist!');
        }

        $commentService = new CommentService();
        $data           = $commentService->userComments($param);
        if (!$data->isEmpty()) {
            $data->load('user,toUser');
        }
        if (empty($this->apiVersion) || $this->apiVersion == '1.0.0') {
            $response = [$data];
        } else {
            $response = ['list' => $data];
        }
        //数据是否存在
        if ($data->isEmpty()) {
            $this->error('No comment!');
        } else {
            $this->success('Successful!', $response);
        }
    }

    /**
     * 删除评论
     */
    public function delComments()
    {
        $input = $this->request->param();
        $id    = '';
        if (!empty($input['id'])) {
            $id = intval($input['id']);
        } else {
            $this->error('Parameter error!');
        }
        $userId = $this->getUserId();
        $result = CommentModel::destroy(['id' => $id, 'user_id' => $userId]);
        if ($result) {
            $this->success('Deleted successfully!');
        } else {
            $this->error('failure');
        }
    }

    /**
     * 添加评论
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function setComments()
    {
        $data = $this->_setComments();
        $res  = CommentModel::setComment($data);
        if ($res) {
            $this->success('success', $res);
        } else {
            $this->error('failure');
        }
    }

    /**
     * 评论数据组织
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function _setComments()
    {
        $input = $this->request->param();
        if (!empty($input['object_id'])) {
            $data['object_id'] = $input['object_id'];
        } else {
            $this->error('Parameter object_id dose not exist!');
        }
        if (!empty($input['table_name'])) {
            $data['table_name'] = $input['table_name'];
        } else {
            $this->error('Parameter table_name dose not exist!');
        }
        if (!empty($input['url'])) {
            $data['url'] = $input['url'];
        } else {
            $this->error('Parameter url dose not exist!');
        }
        if (!empty($input['content'])) {
            $data['content'] = $input['content'];
        } else {
            $this->error('Content cannot be empty!');
        }

        $data['parent_id'] = $this->request->has('parent_id') ? $input['parent_id'] : 0;

        $result = $this->validate($data,
            [
                'object_id' => 'require|number',
                'content'   => 'require',
            ]);
        if (true !== $result) {
            // 验证失败 输出错误信息
            $this->error($result);
        }

        $data['delete_time'] = 0;
        $data['create_time'] = time();
        if ($data['parent_id']) {
            $commentModel = new CommentModel();
            $res          = $commentModel->field(['parent_id', 'path', 'user_id'])->find($data['parent_id']);
            if ($res) {
                $data['path']       = $res['path'] . $data['parent_id'] . ',';
                $data['to_user_id'] = $res['user_id'];
            } else {
                $this->error('Comment does not exist!');
            }
        } else {
            $data['path'] = '0,';
        }
        $data['user_id'] = $this->getUserId();
        $userModel       = new UserModel();
        $userData        = $userModel->find($data['user_id']);
        if (!$userData) {
            $this->error('User does not exist');
        }

        $data['full_name'] = $userData['user_nickname'];
        $data['email']     = $userData['user_email'];
        return $data;
    }
}
