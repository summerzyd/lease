<?php
// +--------------------------------------------------------
// |  PROJECT_NAME: lease
// +--------------------------------------------------------
// |  FILE_NAME: BaseController.php
// +--------------------------------------------------------
// |  AUTHOR: zhengchao
// +--------------------------------------------------------
namespace app\modules\postofficeapp;


use app\common\errors\DataException;
use app\common\errors\RealNameException;
use app\models\dispatch\DriversIdentification;
use app\models\dispatch\RegionDrivers;
use app\models\service\SystemParam;
use app\models\service\SystemParamValue;
use app\services\data\PostOfficeData;

Class PostofficebaseController extends \app\modules\BaseController
{


    public function initialize()
    {

        
        parent::initialize(); // TODO: Change the autogenerated stub

        if (!$this->checkWhiteList()) {
            $driver = DriversIdentification::findFirst(['conditions' => 'driver_id = :driverId:','bind' => ['driverId' => $this->authed->userId]]);

            //已实名认证
            if ($driver && $driver->is_authentication == 2) {
                return true;
            }
            //验证
            // 查询骑手所属快递公司-》所属快递协会
            $RD = RegionDrivers::arrFindFirst([
                'driver_id' => $this->authed->userId,
            ]);

            if (!$RD) {
                $cityId = $this->request->getHeader("cityId");
                $needAuth = (new PostOfficeData())->getPostOfficeSystemParam($cityId, PostOfficeData::RealAuthentication);
                if ($needAuth) {
                    throw new RealNameException();
                }
            } else {
                $subInsId = $RD->ins_id;
                // 查询关联快递协会
                $association =  $this->modelsManager->createBuilder()
                    // 查询快递公司
                    ->addfrom('app\models\users\Institution','i')
                    ->where('i.id = :subInsId:', [
                        'subInsId' => $subInsId,
                    ])
                    // 查询快递协会信息
                    ->join('app\models\users\Association', 'a.ins_id = i.parent_id','a')
                    ->columns('a.*')
                    ->getQuery()
                    ->getSingleResult();
            if (!$association){
                return true;
            }
                $cityId = $association->city_id;

                $needAuth = (new PostOfficeData())->getPostOfficeSystemParam($cityId, PostOfficeData::RealAuthentication);
                if ($needAuth) {
                    throw new RealNameException();
                }
            }

            return true;
        } else {
            return true;
        }


    }

    // 校验实人认证接口白名单
    private function checkWhiteList()
    {
        // 内部白名单
        $whiteList = [
            'Driver' => ['RPBioIDPersonCert', 'RPBioIDPersonCertEnd','BindVehicle'],
            'Store' => ['display'],
        ];
        foreach ($whiteList as $Controller => $ActionList){
            if (strtoupper($Controller) != strtoupper($this->dispatcher->getControllerName())){
                continue;
            }
            foreach ($ActionList as $Action){
                if (strtoupper($Action) == strtoupper($this->dispatcher->getActionName())){
                    return true;
                }
            }
        }
        return $this->auth->isPublic('');
    }
}