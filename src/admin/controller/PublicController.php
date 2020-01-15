<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2017 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: Dean <zxxjjforever@163.com>
// +----------------------------------------------------------------------
namespace api\admin\controller;

use cmf\controller\RestBaseController;
use think\Db;
use think\facade\Validate;

class PublicController extends RestBaseController
{

    // 用户登录 TODO 增加最后登录信息记录,如 ip
    public function login()
    {
        $validate = new \think\Validate([
            'username' => 'require',
            'password' => 'require'
        ]);
        $validate->message([
            'username.require' => 'Please enter your mobile, email or username!',
            'password.require' => 'Password cannot be empty!'
        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }

        $userQuery = Db::name("user");
        if (Validate::is($data['username'], 'email')) {
            $userQuery = $userQuery->where('user_email', $data['username']);
        } else if (cmf_check_mobile($data['username'])) {
            $userQuery = $userQuery->where('mobile', $data['username']);
        } else {
            $userQuery = $userQuery->where('user_login', $data['username']);
        }

        $findUser = $userQuery->find();

        if (empty($findUser)) {
            $this->error('User does not exist!');
        } else {

            switch ($findUser['user_status']) {
                case 0:
                    $this->error('You have been blocked!');
                case 2:
                    $this->error('Account has not been verified!');
            }

            if (!cmf_compare_password($data['password'], $findUser['user_pass'])) {
                $this->error('The password is incorrect!');
            }
        }

        $allowedDeviceTypes = ['mobile', 'android', 'iphone', 'ipad', 'web', 'pc', 'mac'];

        if (empty($this->deviceType) && (empty($data['device_type']) || !in_array($data['device_type'], $this->allowedDeviceTypes))) {
            $this->error('Request error, unknown device!');
        } else if(!empty($data['device_type'])) {
            $this->deviceType = $data['device_type'];
        }

        $userTokenQuery = Db::name("user_token")
            ->where('user_id', $findUser['id'])
            ->where('device_type', $this->deviceType);
        $findUserToken  = $userTokenQuery->find();
        $currentTime    = time();
        $expireTime     = $currentTime + 24 * 3600 * 180;
        $token          = md5(uniqid()) . md5(uniqid());
        if (empty($findUserToken)) {
            $result = Db::name("user_token")->insert([
                'token'       => $token,
                'user_id'     => $findUser['id'],
                'expire_time' => $expireTime,
                'create_time' => $currentTime,
                'device_type' => $this->deviceType
            ]);
        } else {
            $result = Db::name("user_token")
                ->where('user_id', $findUser['id'])
                ->where('device_type', $this->deviceType)
                ->update([
                    'token'       => $token,
                    'expire_time' => $expireTime,
                    'create_time' => $currentTime
                ]);
        }


        if (empty($result)) {
            $this->error('Login failed');
        }

        $this->success('Login successfully', ['token' => $token]);
    }

    // 管理员退出
    public function logout()
    {
        $userId = $this->getUserId();
        Db::name('user_token')->where([
            'token'       => $this->token,
            'user_id'     => $userId,
            'device_type' => $this->deviceType
        ])->update(['token' => '']);

        $this->success('Logout successfully');
    }

}
