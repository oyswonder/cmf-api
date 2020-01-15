<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2017 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: Dean <zxxjjforever@163.com>
// +----------------------------------------------------------------------
namespace api\user\controller;

use cmf\controller\RestUserBaseController;
use think\Db;
use think\Validate;

class ProfileController extends RestUserBaseController
{
    /**
     * 用户密码修改
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function changePassword()
    {
        $validate = new Validate([
            'old_password'     => 'require',
            'password'         => 'require',
            'confirm_password' => 'require|confirm:password'
        ]);

        $validate->message([
            'old_password.require'     => 'Please enter your old password!',
            'password.require'         => 'Please enter your new password!',
            'confirm_password.require' => 'Please enter your confirm password!',
            'confirm_password.confirm' => 'New password and confirm password are inconsistent!'
        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }

        $userId       = $this->getUserId();
        $userPassword = Db::name("user")->where('id', $userId)->value('user_pass');

        if (!cmf_compare_password($data['old_password'], $userPassword)) {
            $this->error('The old password is incorrect!');
        }

        Db::name("user")->where('id', $userId)->update(['user_pass' => cmf_password($data['password'])]);

        $this->success("success!");

    }

    /**
     * 用户绑定邮箱
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function bindingEmail()
    {
        $validate = new Validate([
            'email'             => 'require|email|unique:user,user_email',
            'verification_code' => 'require'
        ]);

        $validate->message([
            'email.require'             => 'Please enter the email you want to bind!',
            'email.email'               => 'Please enter the correct email!',
            'email.unique'              => 'Email account already exists!',
            'verification_code.require' => 'Please enter the verification code!'
        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }

        $userId    = $this->getUserId();
        $userEmail = Db::name("user")->where('id', $userId)->value('user_email');

        if (!empty($userEmail)) {
            $this->error('You have bound your email!');
        }

        $errMsg = cmf_check_verification_code($data['email'], $data['verification_code']);
        if (!empty($errMsg)) {
            $this->error($errMsg);
        }

        Db::name("user")->where('id', $userId)->update(['user_email' => $data['email']]);

        $this->success('success');
    }

    /**
     * 用户绑定手机号
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function bindingMobile()
    {
        $validate = new Validate([
            'mobile'            => 'require|unique:user,mobile',
            'verification_code' => 'require'
        ]);

        $validate->message([
            'mobile.require'            => 'Please enter the mobile you want to bind!',
            'mobile.unique'             => 'Mobile account already exists!',
            'verification_code.require' => 'Please enter the verification code!'
        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }

        if (!cmf_check_mobile($data['mobile'])) {
            $this->error('Please enter the correct mobile number!');
        }


        $userId = $this->getUserId();
        $mobile = Db::name("user")->where('id', $userId)->value('mobile');

        if (!empty($mobile)) {
            $this->error('You have bound your mobile!');
        }

        $errMsg = cmf_check_verification_code($data['mobile'], $data['verification_code']);
        if (!empty($errMsg)) {
            $this->error($errMsg);
        }

        Db::name("user")->where('id', $userId)->update(['mobile' => $data['mobile']]);

        $this->success('success');
    }

    /**
     * 用户基本信息获取及修改
     * @param string $field 需要获取的字段名
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function userInfo($field = '')
    {
        //判断请求为GET，获取信息
        if ($this->request->isGet()) {
            $userId   = $this->getUserId();
            $fieldStr = 'user_type,user_login,mobile,user_email,user_nickname,avatar,signature,user_url,sex,birthday,score,coin,user_status,user_activation_key,create_time,last_login_time,last_login_ip';
            if (empty($field)) {
                $userData = Db::name("user")->field($fieldStr)->find($userId);
            } else {
                $fieldArr     = explode(',', $fieldStr);
                $postFieldArr = explode(',', $field);
                $mixedField   = array_intersect($fieldArr, $postFieldArr);
                if (empty($mixedField)) {
                    $this->error('The information you query does not exist!');
                }
                if (count($mixedField) > 1) {
                    $fieldStr = implode(',', $mixedField);
                    $userData = Db::name("user")->field($fieldStr)->find($userId);
                } else {
                    $userData = Db::name("user")->where('id', $userId)->value($mixedField);
                }
            }
            $this->success('success', $userData);
        }
        //判断请求为POST,修改信息
        if ($this->request->isPost()) {
            $userId   = $this->getUserId();
            $fieldStr = 'user_nickname,avatar,signature,user_url,sex,birthday';
            $data     = $this->request->post();
            if (empty($data)) {
                $this->error('Parameter error!');
            }

            if (!empty($data['birthday'])) {
                $data['birthday'] = strtotime($data['birthday']);
            }

            $upData = Db::name("user")->where('id', $userId)->field($fieldStr)->update($data);
            if ($upData !== false) {
                $this->success('success');
            } else {
                $this->error('failure!');
            }
        }
    }
}
