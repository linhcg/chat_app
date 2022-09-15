<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Auth\DefaultPasswordHasher;
use Cake\Filesystem\Folder;
use Cake\Http\Cookie\Cookie;
use Cake\I18n\Time;
use Cake\ORM\TableRegistry;

/**
 * Users Controller
 *
 * @property \App\Model\Table\UsersTable $Users
 * @method \App\Model\Entity\User[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class UsersController extends AppController
{
    /**
     * beforeFilter
     */
    public function beforeFilter(\Cake\Event\EventInterface $event)
    {
        parent::beforeFilter($event);
        // Configure the login action to not require authentication, preventing
        //    $this->Authentication->addUnauthenticatedActions(['login', 'add','register']);
        //  $this->Authorization->skipAuthorization(['index','view','add','edit','delete','login','logout','register']);
        $this->Auth->allow(['login', 'register', 'logout']);
    }

    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $this->viewBuilder()->setLayout('admin');
        $users = $this->paginate($this->Users);
        $this->set(compact('users'));
    }

    /**
     * phân quyền
     */
    public function isAuthorized($user)
    {
        $action = $this->request->getParam('action');
        if ($user) {
            if (in_array($action, ['login', 'register', 'logout'])) {
                return true;
            }
            if (in_array($action, ['home', 'index', 'view', 'add', 'edit', 'delete', 'profile','chat'])) {
                if ($user['role'] == 1) {
                    return true;
                }
            }
            if (in_array($action, ['home', 'profile', 'search', 'update'])) {
                if ($user['role'] == 0) {
                    return true;
                }
            }
        }

        return parent::isAuthorized($user);
    }

    /**
     * View method
     *
     * @param string|null $id User id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $this->viewBuilder()->setLayout('admin');
        $user = $this->Users->get($id, [
            'contain' => ['Comments', 'Likes', 'Posts'],
        ]);

        $this->set(compact('user'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $this->viewBuilder()->setLayout('admin');
        $user = $this->Users->newEmptyEntity();
        if ($this->request->is('post')) {
            $user = $this->Users->patchEntity($user, $this->request->getData());
            if ($this->Users->save($user)) {
                $this->Flash->success(__('The user has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The user could not be saved. Please, try again.'));
        }
        $this->set(compact('user'));
    }

    /**
     * Edit method
     *
     * @param string|null $id User id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $this->viewBuilder()->setLayout('admin');
        $user = $this->Users->get($id, [
            'contain' => [],
        ]);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $user = $this->Users->patchEntity($user, $this->request->getData());
            if ($this->Users->save($user)) {
                $this->Flash->success(__('The user has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The user could not be saved. Please, try again.'));
        }
        $this->set(compact('user'));
    }

    /**
     * Delete method
     *
     * @param string|null $id User id.
     * @return \Cake\Http\Response|null|void Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->viewBuilder()->setLayout('admin');
        $this->request->allowMethod(['post', 'delete']);
        $user = $this->Users->get($id);
        if ($this->Users->delete($user)) {
            $this->Flash->success(__('The user has been deleted.'));
        } else {
            $this->Flash->error(__('The user could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Đăng nhập
     */
    public function login()
    {
        $this->request->allowMethod(['get', 'post']);
        // check cookie
        $this->checkCookie();

        if ($this->Auth->user()) {
            $user = $this->Auth->user();
            if ($user['role'] == 1) {
                return $this->redirect(['controller' => 'Users', 'action' => 'index']);
            } else {
                return $this->redirect(['controller' => 'Users', 'action' => 'home']);
            }
        }
        if ($this->request->is('post')) {
            $user = $this->Auth->identify();
            if ($user) {
                $this->Auth->setUser($user);
                $data = $this->request->getData();
                // luu vao cookie
                if (isset($data['remember-me'])) {
                    $password = $this->Users->get($user['id'])->password;
                    $cookie = $this->createCookie($user['email'], $password);
                    $this->response = $this->response->withCookie($cookie);
                }
                if ($user['role'] == 1) {
                    // trang admin
                    return $this->redirect(['controller' => 'Users', 'action' => 'index']);
                } else {
                    // trang home user
                    $this->redirect(['controller' => 'Users', 'action' => 'home']);
                }
            } else {
                $this->Flash->error(__('Username or password is incorrect'));
            }
        }
    }

    /**
     * logout
     */
    public function logout()
    {
        $this->Cookies->remove('remember_me_on');

        return $this->redirect($this->Auth->logout())->withCookie(new Cookie('remember_me_cookie', ''));
    }

    /**
     * Đăng ký
     */
    public function register()
    {
        if ($this->Auth->user()) {
            return $this->redirect(['controller' => 'Posts', 'action' => 'index']);
        }
        $this->checkCookie();
        $user = $this->Users->newEmptyEntity();
        if ($this->request->is('post')) {
            $data = $this->request->getData();
            $img = $this->request->getData('image');
            $imageTbale = TableRegistry::getTableLocator()->get('Images');
            $folder = new Folder(WWW_ROOT . 'img' . DS . 'post_image', true);
            $imgName = time() . $img->getClientFilename();
            $imgType = $img->getClientMediaType();
            $targetPath = WWW_ROOT . 'img' . DS . 'post_image' . DS . $imgName;
            //  dd($targetPath);
            if ($imgType == 'image/jpeg' || $imgType == 'image/jpg' || $imgType == 'image/png') {
                if (!empty($imgName)) {
                    if ($img->getSize() > 0 && $img->getError() == 0) {
                        $img->moveTo($targetPath);
                        $user->image = 'post_image/' . $imgName;
                    }
                }
            }

            if ($data['password'] == $data['re-password']) {
                $user->user_name = $data['user_name'];
                $user->email = $data['email'];
                $user->password = $data['password'];
                $user->role = 0;
                if ($this->Users->save($user)) {
                    $this->Auth->setUser($user->toArray());
                    $this->createCookie($user->email, $user->password);
                    $this->Flash->success(__('The user has been saved. Login now!'));

                    return $this->redirect(['controller' => 'Users', 'action' => 'home']);
                }
                $this->Flash->error(__('The user could not be saved. Please, try again.'));
            }
            //  $user = $this->Users->patchEntity($user, $this->request->getData());

            $this->Flash->error(__('Password wrong . Please, try again.'));
        }
        $this->set(compact('user'));
    }

    /**
     * Trang home
     */
    public function home()
    {
        $cookie = $this->request->getCookie('remember_me_cookie', false);
        $user = $this->Auth->user();
        // danh sach user dang follow
        $following = $this->Follows->getFollowingByUserId($user['id']);
        if ($following) {
            // danh sach post cua user-dang follow
            $posts = $this->Posts->getPostsByUserId($user['id']);
            $posts_1 = $this->Posts->getPostsByLikeAndComment(); //dd($posts_1->toArray());
            $posts->union($posts_1);
        } else {
            $posts = $this->Posts->getPostsByLikeAndComment();
        }

        // kiểm tra xem người dùng đã like các bài đăng hay chưa, trả về danh sách kết quả
        $likesPost = [];
        foreach ($posts as $post) {
            if ($this->Likes->isLike($post->id, $user['id'])) {
                $likesPost[] = $post->id;
            }
        }

        $userLoad = $this->Users->get($user['id']);

        // lấy danh sách đã follow user
        $followers = $this->Follows->getFollowersByUserId($user['id'])->limit(5);
        $followers = $followers->toArray();
        // phan trang, hien thi 10 bai
        $posts = $this->paginate($posts, ['limit' => 10]);
        $this->set(compact('posts', 'likesPost', 'userLoad', 'followers', 'user'));
    }

    /**
     * xem profile người dùng: tuyền vào id của user muốn xem
     */
    public function profile($userId)
    {
        $user = $this->Auth->user();
        $owner = $this->Users->get($userId);
        $posts = $this->Posts->getPostsByOwner($owner->id);
        $likesPost = [];
        $images = [];
        foreach ($posts as $post) {
            if ($this->Likes->isLike($post->id, $owner->id)) {
                $likesPost[] = $post->id;
            }
            foreach ($post->images as $img) {
                $images[] = $img;
            }
        }
        $isFollow = false;
        if ($this->Follows->checkFollow($user['id'], $owner->id)) {
            $isFollow = true;
        }
        $totalPosts = $posts->count();
        $posts = $this->paginate($posts, ['limit' => 10]);
        $this->set(compact('posts', 'likesPost', 'owner', 'images', 'isFollow', 'totalPosts'));
    }

    /**
     * Tìm kiếm
     */
    public function search()
    {
        $text = $this->request->getQuery('text');
        if ($text) {
            $user = $this->Auth->user();
            $posts = $this->Posts->getPostsBySearch($text);
            // kiểm tra xem người dùng đã like các bài đăng hay chưa, trả về danh sách kết quả
            $likesPost = [];
            foreach ($posts as $post) {
                if ($this->Likes->isLike($post->id, $user['id'])) {
                    $likesPost[] = $post->id;
                }
            }

            $userLoad = $this->Users->get($user['id']);
            $posts = $this->paginate($posts, ['limit' => 10]);
            $this->set(compact('posts', 'likesPost', 'userLoad'));
        }
    }

    /**
     * Update user
     */
    public function update()
    {
        $userAuth = $this->Auth->user();
        if ($userAuth['id']) {
            $user = $this->Users->get($userAuth['id'], [
                'contain' => [],
            ]);

            if ($this->request->is(['patch', 'post', 'put'])) {
                $data = $this->request->getData();
                // đổi avatar
                if ($this->request->getData('image')) {
                    $img = $this->request->getData('image');
                    $imgName = time() . $img->getClientFilename();
                    $imgType = $img->getClientMediaType();
                    $targetPath = WWW_ROOT . 'img' . DS . 'post_image' . DS . $imgName;
                    //  dd($targetPath);
                    if ($imgType == 'image/jpeg' || $imgType == 'image/jpg' || $imgType == 'image/png') {
                        if (!empty($imgName)) {
                            if ($img->getSize() > 0 && $img->getError() == 0) {
                                $img->moveTo($targetPath);
                                $user->image = 'post_image/' . $imgName;
                            }
                        }
                    }
                }

                // check passwork
                if ((new DefaultPasswordHasher())->check($data['password'], $user->password)) {
                    if ($data['newPassword'] == $data['re-newPassword']) {
                        $user->user_name = $data['user_name'];
                        $user->password = $data['newPassword'];
                        if ($this->Users->save($user)) {
                            $this->Auth->setUser($user->toArray());
                            $this->Flash->success(__('The user has been saved'));

                            return $this->redirect(['action' => 'update']);
                        }
                    }
                }
                $this->Flash->error(__('The user could not be saved. Please, try again.'));

                return $this->redirect(['action' => 'update']);
            }
            $this->set(compact('user'));
        } else {
            $this->Flash->error(__('You do not have access!'));
        }
    }

    /**
     * check cookie
     */
    public function checkCookie()
    {
        // check cookie
        $cookie = $this->request->getCookie('remember_me_cookie', false);

        if ($cookie) {
            $cookie = json_decode($cookie, true);
            $email = $cookie['email'];
            $password = $cookie['password'];
            if ($this->Users->login($email, $password)) {
                $user = $this->Users->login($email, $password);
                $this->Auth->setUser($user);
                if ($user->role == 1) {
                    // trang admin
                    return $this->redirect(['controller' => 'Users', 'action' => 'index']);
                } else {
                    // trang home user
                    $this->redirect(['controller' => 'Users', 'action' => 'home']);
                }
            }
        }
    }

    /**
     * Tạo cookie
     */
    public function createCookie($email, $password)
    {
        $data['email'] = $email;
        $data['password'] = $password;
        $cookie = Cookie::create('remember_me_cookie', $data)
                        ->withExpiry(new Time('+1 year'))
                        ->withHttpOnly(true);
        $this->Cookies->add($cookie);

        return $cookie;
    }

    /**
     * Biểu đồ
     */
    public function chart()
    {
        $this->viewBuilder()->setLayout('admin');
    }
}
