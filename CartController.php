<?php

    /**
     *
     * Контроллер для работы с корзиной
     *
     */


    //Подключаем модели
    include_once 'models/CategoriesModel.php';
    include_once 'models/ProductsModel.php';
    include_once 'models/OrdersModel.php';
    include_once 'models/PurchaseModel.php';

    /**
     *Добавление товара в корзину
     * @param integer id GET параметр - id добавляемого продукта
     * @return json информация об операции(успех, количество элементов в корзине)
     */

    function addtocartAction(){
        $itemId = isset($_GET['id']) ? intval($_GET['id']) : null; // Добавляем в $itemId id товара
        if(! $itemId) return false; // Если переменная равна нулю, то завершаем её

        $resData = array(); // Инициализируем переменную

        //Если значение не найдено, то добавляем
        if(isset($_SESSION['cart']) && array_search($itemId, $_SESSION['cart']) === false){
            $_SESSION['cart'] []=$itemId;
            $resData['cntItems'] = count($_SESSION['cart']);
            $resData['success'] = 1;
        } else {
            $resData['success'] = 0;
        }

        echo json_encode($resData);
    }


    /**
     *Удаление продукта из корзины
     * @param integer id GET пфрфметр - id удаляемого товара из корзины
     * @return json информация об операции (Успех, количество элементов в корзине)
     */

    function removefromcartAction(){
        $itemId = isset($_GET['id']) ? intval($_GET['id']) : null;
        if(! $itemId) exit();

        $resData = array();
        $key = array_search($itemId, $_SESSION['cart']);
        if($key !== false){  // !== строгое неравенство
            unset($_SESSION['cart'][$key]);
            $resData['success'] = 1;
            $resData['cntItems'] = count($_SESSION['cart']);
        } else {
            $resData['success'] = 0;
        }

        echo json_encode($resData);
    }


    /**
     *Формирование страницы корзины
     * @link /cart/
     */

    function indexAction($smarty){

        $itemsIds = isset($_SESSION['cart']) ? $_SESSION['cart'] : array();

        $rsCategories = getAllMainCatsWithChildren();
        $rsProducts = getProductFromArray($itemsIds);


        $smarty->assign('pageTitle', 'Корзина');
        $smarty->assign('rsCategories', $rsCategories);
        $smarty->assign('rsProducts', $rsProducts);

        loadTemplate($smarty, 'header');
        loadTemplate($smarty, 'cart');
        loadTemplate($smarty, 'footer');

    }

    /**
     * Формирование страницы заказа
     */

    function orderAction($smarty){

        // Получаем массив id товаров из корзины
        $itemIds = isset($_SESSION['cart']) ? $_SESSION['cart'] : null;
        //если корзина пуста, то переходим в корзину
        if (! $itemIds){
            redirect('/cart/');
            return;
        }

        // Получаем из массива $_POST количество покупаемых товаров
        $itemsIds = array();
        foreach ($itemIds as $item) {
            // Формируем ключ для массива POST
            $postVar = 'itemCnt_' . $item;
            //Создаем элемент массива количества покупаемого товара
            // Ключ массива - id товара, значение массива - количество товара
            // $itemCnt[1] = 3; товар с айди=1 покупают 3 раза
            $itemsCnt[$item] = isset($_POST[$postVar]) ? $_POST[$postVar] : null;
        }

        //Получаем список продуктов по массиву корзины
        $rsProducts = getProductFromArray($itemIds);

        //Добавляем каждому товару дополнительное поле
        //"realPrice = количество товара * на цену продукта"
        //"cnt" = количество покупаемого товара

        //&$item - для того, чтобы при изменении переменной $item
        //менялся и элемент массива $rsProducts
        $i = 0;
        foreach ($rsProducts as &$item){
            $item['cnt'] = isset($itemsCnt[$item['id']]) ? $itemsCnt[$item['id']] : 0;
            if($item['cnt']){
                $item['realPrice'] = $item['cnt'] * $item['price'];
            } else {
                //Если вдруг получилось так, что товар в корзине есть, но его количество равно нулю
                //То удаляем этот товар
                unset($rsProducts[$i]);
            }
            $i++;
        }

        if(! $rsProducts){
            echo "Корзина пуста";
            return;
        }

        //Полученный массив покупаемых товаров помещаем в сессионную переменную
        $_SESSION['saleCart'] = $rsProducts;

        $rsCategories = getAllMainCatsWithChildren();

        //hideLoginBox переменная - флаг для того, чтобы спрятать блоки логина и регистрации в боковой панели
        if (! isset($_SESSION['user'])){
            $smarty->assign('hideLoginBox', 1);

        }

        $smarty->assign('pageTitle', 'Заказ');
        $smarty->assign('rsCategories', $rsCategories);
        $smarty->assign('rsProducts', $rsProducts);

        loadTemplate($smarty, 'header');
        loadTemplate($smarty, 'order');
        loadTemplate($smarty, 'footer');

    }


        /**
         *AJAX функция сохранения заказа
         * @param array $_SESSION['saleCart'] массив получаемых продуктов
         * @return json информация о результате выполнения
         */
function saveorderAction(){
    //Получение массива покупаемых товаров
    $cart = isset($_SESSION['saleCart']) ? $_SESSION['saleCart'] : null;
    //Если корзина пустая, то формируется ответ с ошибкой
    if (! $cart){
        $resData['success'] = 0;
        $resData['message'] = 'Нет товаров для заказа';
        echo json_encode($resData);
        return;
    }
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    //Создание нового заказа и получение его ID
    $orderId = makeNewOrder($name, $phone, $address);

    //Если заказ не создан, то функция выдаст ошибку и завершится
    if(! $orderId){
        $resData['success'] = 0;
        $resData['message'] = 'Ошибка создания заказа';
        echo json_encode($resData);
        return;
    }
    //Созданение товаров для созданного заказа
    $res = setPurchaseForOrder($orderId, $cart);
    //Если функция выполнена успешно, то формируется ответ и удаляются переменные корзины
    if ($res){
        $resData['success'] = 1;
        $resData['message'] = 'Заказ сохранен';
        unset($_SESSION['saleCart']);
        unset($_SESSION['cart']);
    }
    else{
        $resData['success'] = 0;
        $resData['message'] = 'Ошибка внесения данных для заказа №' . $orderId;
    }

    echo  json_encode($resData);
}
