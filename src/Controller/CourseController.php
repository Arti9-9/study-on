<?php

namespace App\Controller;

use App\Dto\CourseDto;
use App\Dto\PayDto;
use App\Dto\TransactionDto;
use App\Entity\Course;
use App\Exception\BillingUnavailableException;
use App\Exception\ClientException;
use App\Form\CourseType;
use App\Repository\CourseRepository;
use App\Repository\LessonRepository;
use App\Service\BillingClient;
use App\Service\DecodingJwt;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/courses")
 */
class CourseController extends AbstractController
{
    /**
     * @Route("/", name="app_course_index", methods={"GET"})
     */
    public function index(CourseRepository $courseRepository, BillingClient $billingClient, DecodingJwt $decodingJwt): Response
    {
        try {
            /** @var CourseDto[] $coursesDto */
            $coursesDto = $billingClient->getAllCourses();
            // Создаем массив, где вместо индексов code, для удобства работы с курсами
            $coursesInfoBilling = [];
            foreach ($coursesDto as $courseDto) {
                $coursesInfoBilling[$courseDto->getCode()] = [
                    'course' => $courseDto,
                    'transaction' => null,
                ];
            }
            // Если пользователь не авторизован
            if (!$this->getUser()) {
                return $this->render('course/index.html.twig', [
                    'courses' => $courseRepository->findBy([], ['id' => 'ASC']),
                    'coursesInfoBilling' => $coursesInfoBilling,
                ]);
            }


            // Нам нужны транзакции оплаты курсов пользователя, а также нам нужно пропустить курсы,
            // аренда которых уже завершилась
            /** @var TransactionDto[] $transactionsDto */
            $transactionsDto = $billingClient->transactionsHistory($this->getUser(), 'type=payment&skip_expired=true');

            foreach ($coursesDto as $courseDto) {
                foreach ($transactionsDto as $transactionDto) {
                    if ($transactionDto->getCourseCode() === $courseDto->getCode()) {
                        $coursesInfoBilling[$courseDto->getCode()] = [
                            'course' => $courseDto,
                            'transaction' => $transactionDto,
                        ];
                        break;
                    }

                    $coursesInfoBilling[$courseDto->getCode()] = [
                        'course' => $courseDto,
                        'transaction' => null,
                    ];
                }
            }

            // Получим баланс пользователя
            $data = $billingClient->getCurrentUser($this->getUser(), $decodingJwt);
            $balance = $data['balance'];

            return $this->render('course/index.html.twig', [
                'courses' => $courseRepository->findBy([], ['id' => 'ASC']),
                'coursesInfoBilling' => $coursesInfoBilling,
                'balance' => $balance,
            ]);
        } catch (BillingUnavailableException $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * @Route("/new", name="app_course_new", methods={"GET", "POST"})
     * @IsGranted("ROLE_SUPER_ADMIN")
     */
    public function new(Request $request, CourseRepository $courseRepository, BillingClient $billingClient): Response
    {
        $course = new Course();
        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $courseDto = new CourseDto();

                $courseDto->setTitle($form->get('title')->getData());
                $courseDto->setCode($form->get('characterCode')->getData());
                $courseDto->setType($form->get('type')->getData());
                if ('free' === $form->get('type')->getData()) {
                    $courseDto->setPrice(0);
                } else {
                    $courseDto->setPrice($form->get('price')->getData());
                }

                // Отдаем запрос к биллингу, и получаем ответ
                $response = $billingClient->newCourse($this->getUser(), $courseDto);

                // Если всё ок, то добавляем курс в БД, иначе вызовится исключение с ошибкой
                $courseRepository->add($course);
            } catch (BillingUnavailableException | \Exception $e) {
                return $this->render('course/new.html.twig', [
                    'course' => $course,
                    'form' => $form->createView(),
                    'errors' => $e->getMessage(),
                ]);
            }

            // flash message
            $this->addFlash('success', 'Новый курс успешно добавлен!');
            return $this->redirectToRoute('app_course_index');
        }

        return $this->render('course/new.html.twig', [
            'course' => $course,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="app_course_show", methods={"GET"})
     */
    public function show(Course $course, LessonRepository $lessonRepository, BillingClient $billingClient): Response
    {
        try {
            // Проверим сначало является ли пользователь администратором
            if ($this->getUser() && $this->getUser()->getRoles()[0] === 'ROLE_SUPER_ADMIN') {
                $lessons = $lessonRepository->findByCourse($course);

                return $this->render('course/show.html.twig', [
                    'course' => $course,
                    'lessons' => $lessons,
                ]);
            }

            // Далее проверим, что курс который собираются открыть - бесплатный
            /** @var CourseDto $courseDto */
            $courseDto = $billingClient->getCourse($course->getCharacterCode());
            // Если он бесплатный, тогда ОК
            if ($courseDto->getType() === 'free') {
                $lessons = $lessonRepository->findByCourse($course);

                return $this->render('course/show.html.twig', [
                    'course' => $course,
                    'lessons' => $lessons,
                ]);
            }

            // Если курс платный, а пользователь не авторизован, то выдаем ошибку
            if (!$this->getUser()) {
                throw new ClientException('Доступ запрещен, авторизуйтесь или зарегистрируйтесь.');
            }

            // Если пользователь авторизован, то нам надо проверить историю его транзакций с этим курсом, имеет ли
            // пользователь доступ к нему

            // Нам нужно найти транзакцию оплаты курса пользователем,
            // также мы отбрасываем курсы, аренда которых уже завершилась
            /** @var TransactionDto[] $transactionsDto */
            $transactionDto = $billingClient->transactionsHistory(
                $this->getUser(),
                'type=payment&course_code=' . $course->getCharacterCode() . '&skip_expired=true'
            );

            // Если такая тразакция существует, то мы выдадим курс
            if ($transactionDto !== []) {
                $lessons = $lessonRepository->findByCourse($course);

                return $this->render('course/show.html.twig', [
                    'course' => $course,
                    'lessons' => $lessons,
                ]);
            }

            // Иначе ошибка
            throw new AccessDeniedException('Доступ запрещен.');
        } catch (BillingUnavailableException $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * @Route("/{id}/edit",  requirements={"id" ="\d+"} ,name="app_course_edit", methods={"GET", "POST"})
     * @IsGranted("ROLE_SUPER_ADMIN")
     */
    public function edit(Request $request, Course $course, CourseRepository $courseRepository,BillingClient $billingClient): Response
    {

        // Проверим что действительно есть такой курс
        $courseCode = $course->getCharacterCode();
        try {
            $courseTemp = $billingClient->getCourse($courseCode);
        } catch (BillingUnavailableException $e) {
            throw new \Exception($e);
        }

        $form = $this->createForm(CourseType::class, $course,['price'=>$courseTemp->getPrice(), 'type'=>$courseTemp->getType()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $courseDto = new CourseDto();
                $courseDto->setTitle($form->get('title')->getData());
                $courseDto->setCode($form->get('characterCode')->getData());
                $courseDto->setType($form->get('type')->getData());
                if ('free' === $form->get('type')->getData()) {
                    $courseDto->setPrice(0);
                } else {
                    $courseDto->setPrice($form->get('price')->getData());
                }

                // Отдаем запрос к биллингу, и получаем ответ
                $response = $billingClient->editCourse($this->getUser(), $courseCode, $courseDto);

                // Если всё ок, то обновляем даныне о курсе в БД
                $courseRepository->add($course);
            } catch (BillingUnavailableException|\Exception $e) {
                return $this->render('course/edit.html.twig', [
                    'course' => $course,
                    'form' => $form->createView(),
                    'errors' => $e->getMessage(),
                ]);
            }
        }
        return $this->renderForm('course/edit.html.twig', [
            'course' => $course,
            'form' => $form,
        ]);
    }

    /**
     * @Route("/{id}/pay", name="app_course_pay", methods={"GET"})
     * @IsGranted("IS_AUTHENTICATED_FULLY")
     */
    public function pay(Course $course, BillingClient $billingClient): Response
    {


        $courseCode = $course->getCharacterCode();
        try {
            /** @var PayDto $payDto */
            $payDto = $billingClient->paymentCourse($this->getUser(), $courseCode);
            // flash message
            $this->addFlash('success', 'Оплата прошла успешно! Наслаждайтесь курсом!');
        } catch (BillingUnavailableException $e) {
            throw new \Exception($e->getMessage());
        }

        return $this->redirectToRoute('app_course_index', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * @Route("/{id}",  requirements={"id" ="\d+"} ,name="app_course_delete", methods={"POST"})
     * @IsGranted("ROLE_SUPER_ADMIN")
     */
    public function delete(Request $request, Course $course, CourseRepository $courseRepository): Response
    {
        if ($this->isCsrfTokenValid('delete' . $course->getId(), $request->request->get('_token'))) {
            $courseRepository->remove($course);
        }

        return $this->redirectToRoute('app_course_index', [], Response::HTTP_SEE_OTHER);
    }
}
