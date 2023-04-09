    <?php

    // Подключаем модели
    include_once 'models/CategoriesModel.php';
    include_once 'models/ProductsModel.php';

     function testAction(){
         echo 'IndexController.php > testAction';
     }

    function indexAction($smarty){

         $rsCategories = getAllMainCatsWithChildren();
         $rsProducts = getLastProducts();

         $smarty->assign('pageTitle', 'Главная страница сайта');
         $smarty->assign('rsCategories', $rsCategories);
         $smarty->assign('rsProducts', $rsProducts);

        loadTemplate($smarty,'header');
        loadTemplate($smarty,'index'); // Функция загрузки шаблона Index.tpl
        loadTemplate($smarty,'footer');
    }

