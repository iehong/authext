<?php

namespace Iehong\AuthExt\Http\Controllers;

use Dcat\Admin\Layout\Content;
use Dcat\Admin\Admin;
use Illuminate\Http\Request;
use Dcat\Admin\Form;
use Dcat\Admin\Http\Repositories\Administrator;
use Dcat\Admin\Grid;
use Iehong\AuthExt\AuthextServiceProvider;
use Iehong\AuthExt\Models\Administrator as AdministratorModel;
use Dcat\Admin\Http\Controllers\UserController as BaseC;
use Dcat\Admin\Show;
use Dcat\Admin\Support\Helper;
use Dcat\Admin\Widgets\Tree;

class UserController extends BaseC
{
  protected function grid()
  {
    return Grid::make(Administrator::with(['roles']), function (Grid $grid) {
      $grid->column('phone', AuthextServiceProvider::trans('authext.phone'));
      $grid->column('name');
      if (config('admin.permission.enable')) {
        $grid->column('roles')->pluck('name')->label('primary', 3);
        $permissionModel = config('admin.database.permissions_model');
        $roleModel = config('admin.database.roles_model');
        $nodes = (new $permissionModel())->allNodes();
        $grid->column('permissions')
          ->if(function () {
            return !$this->roles->isEmpty();
          })
          ->showTreeInDialog(function (Grid\Displayers\DialogTree $tree) use (&$nodes, $roleModel) {
            $tree->nodes($nodes);
            foreach (array_column($this->roles->toArray(), 'slug') as $slug) {
              if ($roleModel::isAdministrator($slug)) {
                $tree->checkAll();
              }
            }
          })
          ->else()
          ->display('');
      }
      $grid->column('created_at');
      $grid->column('updated_at')->sortable();
      $grid->quickSearch(['id', 'name', 'username']);
      $grid->showQuickEditButton();
      $grid->enableDialogCreate();
      $grid->showColumnSelector();
      $grid->disableEditButton();
      $grid->actions(function (Grid\Displayers\Actions $actions) {
        if ($actions->getKey() == AdministratorModel::DEFAULT_ID) {
          $actions->disableDelete();
        }
      });
    });
  }

  public function form()
  {
    return Form::make(Administrator::with(['roles']), function (Form $form) {
      $userTable = config('admin.database.users_table');
      $connection = config('admin.database.connection');
      $id = $form->getKey();
      $form->display('id', 'ID');
      $form->text('phone', AuthextServiceProvider::trans('authext.phone'))
        ->required()
        ->creationRules(['required', "unique:{$connection}.{$userTable}"])
        ->updateRules(['required', "unique:{$connection}.{$userTable},phone,$id"]);
      $form->text('name', trans('admin.name'))->required();
      $form->image('avatar', trans('admin.avatar'))->autoUpload();
      if (config('admin.permission.enable')) {
        $form->multipleSelect('roles', trans('admin.roles'))
          ->options(function () {
            $roleModel = config('admin.database.roles_model');
            return $roleModel::all()->pluck('name', 'id');
          })
          ->customFormat(function ($v) {
            return array_column($v, 'id');
          });
      }
      $form->display('created_at', trans('admin.created_at'));
      $form->display('updated_at', trans('admin.updated_at'));
      if ($id == AdministratorModel::DEFAULT_ID) {
        $form->disableDeleteButton();
      }
    })->saving(function (Form $form) {
    });
  }
  protected function detail($id)
  {
    return Show::make($id, Administrator::with(['roles']), function (Show $show) {
      $show->field('id');
      $show->field('phone', AuthextServiceProvider::trans('authext.phone'));
      $show->field('name');
      $show->field('avatar', __('admin.avatar'))->image();
      if (config('admin.permission.enable')) {
        $show->field('roles')->as(function ($roles) {
          if (!$roles) {
            return;
          }
          return collect($roles)->pluck('name');
        })->label();
        $show->field('permissions')->unescape()->as(function () {
          $roles = $this->roles->toArray();
          $permissionModel = config('admin.database.permissions_model');
          $roleModel = config('admin.database.roles_model');
          $permissionModel = new $permissionModel();
          $nodes = $permissionModel->allNodes();
          $tree = Tree::make($nodes);
          $isAdministrator = false;
          foreach (array_column($roles, 'slug') as $slug) {
            if ($roleModel::isAdministrator($slug)) {
              $tree->checkAll();
              $isAdministrator = true;
            }
          }
          if (!$isAdministrator) {
            $keyName = $permissionModel->getKeyName();
            $tree->check(
              $roleModel::getPermissionId(array_column($roles, $keyName))->flatten()
            );
          }
          return $tree->render();
        });
      }
      $show->field('created_at');
      $show->field('updated_at');
    });
  }
}
