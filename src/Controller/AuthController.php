<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Repository\CustomerRepository;
use \Firebase\JWT\JWT;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class AuthController extends AbstractController
{

    /**
     * @Route("/auth/register", name="register", methods={"POST"})
     */
    public function register(Request $request, UserPasswordEncoderInterface $encoder)
    {
        $password = $request->get('password');
        $email = $request->get('email');
        $user = new Customer();
        $user->setPassword($encoder->encodePassword($user, $password));
        $user->setEmail($email);
        $em = $this->getDoctrine()->getManager();
        $em->persist($user);
        $em->flush();
        return $this->json([
            'user' => $user->getEmail()
        ]);
    }

        /**
         * @Route("/auth/login", name="login", methods={"POST"})
         */
        public function login(Request $request, CustomerRepository $userRepository, UserPasswordEncoderInterface $encoder)
        {
                $user = $userRepository->findOneBy([
                        'email'=>$request->get('email'),
                ]);
                if (!$user || !$encoder->isPasswordValid($user, $request->get('password'))) {
                        return $this->json([
                            'message' => 'email or password is wrong.',
                        ]);
                }
                $payload = [
                    "user" => $user->getUsername(),
                    "exp"  => (new \DateTime())->modify("+2 day")->getTimestamp(),
                ];


                $jwt = JWT::encode($payload, $this->getParameter('jwt_secret'), 'HS256');
                return $this->json([
                    'message' => 'success!',
                    'token' => sprintf('Bearer %s', $jwt),
                ]);
        }

}