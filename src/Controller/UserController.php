<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\CustomerRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Annotation\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use OpenApi\Annotations as OA;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Class UserController
 * @package App\Controller
 * @Route("/api")
 */
class UserController extends AbstractController
{

    const EXPIRES_AFTER = 3600;

    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    /**
     * @Route("/users/customers/{id}", name="customers_users",methods={"GET"},requirements = {"id"="\d+"})
     * @OA\Response(
     *     response=200,
     *     description="Success",
     * )
     * @OA\Response(
     *     response=401,
     *     description="UNAUTHORIZED - JWT Token not found | Expired JWT Token | Invalid JWT Token"
     * )
     * @OA\Tag(name="Users")
     * @Security(name="Bearer")
     */
    public function getAllUsersWhoHaveAConnectionWithACustomer(CustomerRepository $customerRepo,int $id,SerializerInterface $serializer)
    {
        $value = $this->cache->get('cache_all_users_with_a_customer', function (ItemInterface $item) use ($customerRepo,$id) {
            $item->expiresAfter(self::EXPIRES_AFTER);
            return $customerRepo->findOneById($id)->getUsers()->toArray();
        });

//        foreach($value as $key => $item)
//        {
//            $item->_links = '/users/' . $item->getId() . '/customers/' . $id;
//            $value[$key] = $item;
//        }

        return new JsonResponse($serializer->serialize($value,"json",
            ["groups" => "getUsers"])
        , JsonResponse::HTTP_OK,
        [],
        true
        );
    }

    /**
     * @Route("/users/{userId}/customers/{id}", name="customer_one_user",methods={"GET"})
     * @OA\Response(
     *     response=200,
     *     description="Success",
     * )
     * @OA\Response(
     *     response=401,
     *     description="UNAUTHORIZED - JWT Token not found | Expired JWT Token | Invalid JWT Token"
     * )
     * @OA\Tag(name="Users")
     * @Security(name="Bearer")
     */
    public function getOneUserWhoHaveAConnectionWithACustomer(
        UserRepository $userRepo,int $id,int $userId,SerializerInterface $serializer)
    {
        $value = $this->cache->get('cache_user_with_a_customer_'.$userId, function (ItemInterface $item) use ($userRepo,$userId) {
            $item->expiresAfter(self::EXPIRES_AFTER);
            return $userRepo->findOneById($userId);
        });

        if($value->getCustomer()->getId() === $id){
            return new JsonResponse($serializer->serialize($value,"json",
                ["groups" => ["show_one_user"]])
                , JsonResponse::HTTP_OK,
                [],
                true
            );
        }
    }

    /**
     * @Route("/users/customers/{id}", name="add_user_for_customers",methods={"POST"})
     * @OA\Parameter(
     *   name="Phone",
     *   description="Add user",
     *   in="query",
     *   required=true,
     *   @OA\Schema(
     *     type="object",
     *     title="User field",
     *     @OA\Property(property="username", type="string"),
     *     @OA\Property(property="age", type="integer"),
     *     )
     * )
     * @OA\Response(
     *      response=200,
     *      description="Success",
     * )
     * @OA\Response(
     *     response=400,
     *     description="BAD REQUEST"
     * )
     * @OA\Response(
     *     response=401,
     *     description="UNAUTHORIZED - JWT Token not found | Expired JWT Token | Invalid JWT Token"
     * )
     * @OA\Response(
     *     response=403,
     *     description="ACCESS DENIED"
     * )
     * @OA\Tag(name="Users")
     * @Security(name="Bearer")
     */
    public function addANewUserLinkedToACustomer(Request $request,EntityManagerInterface $em,int $id,CustomerRepository $customerRepo)
    {
        $customer = $customerRepo->findOneById($id);
        $user = new User();
        $user->setUsername($request->get('username'));
        $user->setAge($request->get('age'));
        $user->setCustomer($customer);

        $em->persist($user);

        $em->flush();

        $this->cache->delete('cache_all_users_with_a_customer');
        $this->cache->delete('cache_user_with_a_customer');

        return $this->json($user,JsonResponse::HTTP_OK,[],["groups" => ["show_one_user","getCustomer"]]);
    }

    /**
     * @Route("/users/{userId}/customers/{customerId}", name="delete_user",methods={"DELETE"})
     * @OA\Response(
     *      response=200,
     *      description="Success",
     * )
     * @OA\Response(
     *     response=400,
     *     description="BAD REQUEST"
     * )
     * @OA\Response(
     *     response=401,
     *     description="UNAUTHORIZED - JWT Token not found | Expired JWT Token | Invalid JWT Token"
     * )
     * @OA\Response(
     *     response=403,
     *     description="ACCESS DENIED"
     * )
     * @OA\Tag(name="Users")
     * @Security(name="Bearer")
     */
    public function deleteUser(int $userId,int $customerId,UserRepository $userRepo,CustomerRepository $customerRepo,EntityManagerInterface $em)
    {
        $user = $userRepo->findOneById($userId);

        if($user->getCustomer()->getId() === $customerRepo->findOneById($customerId)->getId()){
            $em->remove($user);
            $em->flush();
            $this->cache->delete('cache_all_users_with_a_customer');
            $this->cache->delete('cache_user_with_a_customer');
            return $this->json('User '.$user->getUsername().' is deleted',200);
        }

        return $this->json('User '.$user->getUsername().' is not your user',403);
    }
}
