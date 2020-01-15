<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2017 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: Dean <zxxjjforever@163.com>
// +----------------------------------------------------------------------
namespace api\wxapp\controller;

use think\Db;
use cmf\controller\RestBaseController;
use wxapp\aes\WXBizDataCrypt;
use think\Validate;

class PublicController extends RestBaseController
{
    // 微信小程序用户登录 TODO 增加最后登录信息记录,如 ip
    public function login()
    {
        $validate = new Validate([
            'code'           => 'require',
            'encrypted_data' => 'require',
            'iv'             => 'require',
            'raw_data'       => 'require',
            'signature'      => 'require',
        ]);

        $validate->message([
            'code.require'           => 'Missing parameter code!',
            'encrypted_data.require' => 'Missing parameter encrypted_data!',
            'iv.require'             => 'Missing parameter iv!',
            'raw_data.require'       => 'Missing parameter raw_data!',
            'signature.require'      => 'Missing parameter signature!',
        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }

        $code          = $data['code'];
        $wxappSettings = cmf_get_option('wxapp_settings');

        $appId = $this->request->header('XX-Wxapp-AppId');
        if (empty($appId)) {
            if (empty($wxappSettings['default'])) {
                $this->error('No default WXAPP set');
            } else {
                $defaultWxapp = $wxappSettings['default'];
                $appId        = $defaultWxapp['app_id'];
                $appSecret    = $defaultWxapp['app_secret'];
            }
        } else {
            if (empty($wxappSettings['wxapps'][$appId])) {
                $this->error('WXAPP settings do not exist!');
            } else {
                $appId     = $wxappSettings['wxapps'][$appId]['app_id'];
                $appSecret = $wxappSettings['wxapps'][$appId]['app_secret'];
            }
        }


        $response = cmf_curl_get("https://api.weixin.qq.com/sns/jscode2session?appid=$appId&secret=$appSecret&js_code=$code&grant_type=authorization_code");

        $response = json_decode($response, true);
        if (!empty($response['errcode'])) {
            $this->error('Operation failed!');
        }

        $openid     = $response['openid'];
        $sessionKey = $response['session_key'];

        $pc      = new WXBizDataCrypt($appId, $sessionKey);
        $errCode = $pc->decryptData($data['encrypted_data'], $data['iv'], $wxUserData);

        if ($errCode != 0) {
            $this->error('Operation failed!');
        }

        $findThirdPartyUser = Db::name("third_party_user")
            ->where('openid', $openid)
            ->where('app_id', $appId)
            ->find();

        $currentTime = time();
        $ip          = $this->request->ip(0, true);

        $wxUserData['sessionKey'] = $sessionKey;
        unset($wxUserData['watermark']);

        if ($findThirdPartyUser) {
            $userId = $findThirdPartyUser['user_id'];
            $token  = cmf_generate_user_token($findThirdPartyUser['user_id'], 'wxapp');

            $userData = [
                'last_login_ip'   => $ip,
                'last_login_time' => $currentTime,
                'login_times'     => Db::raw('login_times+1'),
                'more'            => json_encode($wxUserData)
            ];

            if (isset($wxUserData['unionId'])) {
                $userData['union_id'] = $wxUserData['unionId'];
            }

            Db::name("third_party_user")
                ->where('openid', $openid)
                ->where('app_id', $appId)
                ->update($userData);

        } else {

            //TODO 使用事务做用户注册
            $userId = Db::name("user")->insertGetId([
                'create_time'     => $currentTime,
                'user_status'     => 1,
                'user_type'       => 2,
                'sex'             => $wxUserData['gender'],
                'user_nickname'   => $wxUserData['nickName'],
                'avatar'          => $wxUserData['avatarUrl'],
                'last_login_ip'   => $ip,
                'last_login_time' => $currentTime,
            ]);

            Db::name("third_party_user")->insert([
                'openid'          => $openid,
                'user_id'         => $userId,
                'third_party'     => 'wxapp',
                'app_id'          => $appId,
                'last_login_ip'   => $ip,
                'union_id'        => isset($wxUserData['unionId']) ? $wxUserData['unionId'] : '',
                'last_login_time' => $currentTime,
                'create_time'     => $currentTime,
                'login_times'     => 1,
                'status'          => 1,
                'more'            => json_encode($wxUserData)
            ]);

            $token = cmf_generate_user_token($userId, 'wxapp');

        }

        $user = Db::name('user')->where('id', $userId)->find();

        $this->success('Login successful', ['token' => $token, 'user' => $user]);


    }

}
