<?php

namespace App\Tests;

use App\DataFixtures\AppFixtures;
use App\Entity\Course;

class CourseTest extends AbstractTest
{
    // Стартовая страница курсов
    private $startingPath = '/courses';

    // Переопределение метода для фикстур
    protected function getFixtures(): array
    {
        return [AppFixtures::class];
    }

    // Метод вызова старовой страницы курсов
    public function getPath(): string
    {
        return $this->startingPath;
    }

    // Проверка на корректный http-статус для всех GET/POST методов, по всем существующим курсам

    /**
     * @dataProvider urlProviderSuccessful
     * @param $url
     */
    public function testPageIsSuccessful($url): void
    {
        $client = self::getClient();
        $client->request('GET', $url);
        $this->assertResponseOk();
    }

    public function urlProviderSuccessful()
    {
        yield [$this->getPath() . '/'];
        yield [$this->getPath() . '/new'];
    }

    public function testPageSpecificCourseIsSuccessful(): void
    {
        $em = self::getEntityManager();
        $course = $em->getRepository(Course::class)->findOneBy([]);
        self::assertNotEmpty($course);

        // с помощью полученных курсов проходим все возможные страницы GET/POST связанных с курсом

        self::getClient()->request('GET', $this->getPath() . '/' . $course->getId());
        $this->assertResponseOk();

        self::getClient()->request('GET', $this->getPath() . '/' . $course->getId() . '/edit');
        $this->assertResponseOk();

        self::getClient()->request('POST', $this->getPath() . '/' . $course->getId() . '/edit');
        $this->assertResponseOk();
    }
    // Пример проверки 404 ошибки, переход на несуществующие страницы

    /**
     * @dataProvider urlProviderNotFound
     * @param $url
     */
    public function testPageIsNotFound($url): void
    {
        $client = self::getClient();
        $client->request('GET', $url);
        $this->assertResponseNotFound();
    }

    public function urlProviderNotFound()
    {
        yield ['/non'];
        yield [$this->getPath() . '/10'];
    }

    // Тесты главной страницы курсов
    public function testCourseIndex(): void
    {
        $client = self::getClient();
        $crawler = $client->request('GET', $this->getPath() . '/');
        $this->assertResponseOk();

        //  Получаем фактическое количество курсов из БД
        $em = self::getEntityManager();
        $courses = $em->getRepository(Course::class)->findAll();
        self::assertNotEmpty($courses);
        $coursesCountFromBD = count($courses);

        // Получение количества курсов по фильтрации класса card
        $coursesCount = $crawler->filter('div.card')->count();

        // Проверка количества курсов на странице
        self::assertEquals($coursesCountFromBD, $coursesCount);
    }

    // Тесты страницы конкретного курса
    public function testCourseShow(): void
    {
        $em = self::getEntityManager();
        $courses = $em->getRepository(Course::class)->findAll();
        self::assertNotEmpty($courses);

        foreach ($courses as $course) {
            $crawler = self::getClient()->request('GET', $this->getPath() . '/' . $course->getId());
            $this->assertResponseOk();

            // Провекра количества уроков для конкретного курса
            $lessonsCount = $crawler->filter('ol > li')->count();
            // Получаем фактическое количество уроков для данного курса из БД
            $lessonsCountFromBD = count($course->getLessons());

            // Проверка количества уроков в курсе
            static::assertEquals($lessonsCountFromBD, $lessonsCount);
        }
    }

    // Тест страницы добавления курса с валидными значениями,
    // а также проверка редиректа на страницу с курсами и изменения их количества
    // после добавления курса. А также проверить удаление курса.
    public function testCourseNewAddValidFieldsAndDeleteCourse(): void
    {
        // Стартовая точка на главной странице с курсами
        $client = self::getClient();
        $crawler = $client->request('GET', $this->getPath() . '/');
        $this->assertResponseOk();

        // Перейдём к добавлению  (форме)
        $link = $crawler->filter('a.course-new')->link();
        $client->click($link);
        $this->assertResponseOk();

        // Заполнение полей формы
        $client->submitForm('course-add', [
            'course[characterCode]' => 'KKKK',
            'course[title]' => 'Новый курс',
            'course[description]' => 'Тестовый курс',
        ]);
        // Проверка редиректа на главную страницу
        self::assertTrue($client->getResponse()->isRedirect($this->getPath() . '/'));
        // Переходим на страницу редиректа
        $crawler = $client->followRedirect();

        // Получение количества курсов
        $coursesCount = $crawler->filter('div.card')->count();

        // Проверка обновленного количества курсов на странице
        // (можно сранивать и с фактическим количеством курсов из БД)
        self::assertEquals(4, $coursesCount);

        // Перейдём на страницу добавленного курса
        $link = $crawler->filter('a.card-link')->last()->link();
        $client->click($link);
        $this->assertResponseOk();

        // Нажимаме кнопку удалить
        $client->submitForm('course-delete');
        // Проверка редиректа на галвную страницу
        self::assertTrue($client->getResponse()->isRedirect($this->getPath() . '/'));
        // Переходим на страницу редиректа
        $crawler = $client->followRedirect();
        $this->assertResponseOk();

        // Проверяем количество курсов после удаления
        $coursesCount = $crawler->filter('div.card')->count();
        self::assertEquals(3, $coursesCount);
    }

    // Тест страницы добавления курса с невалидным полем code
    public function testCourseNewAddNotValidCode(): void
    {
        $client = self::getClient();
        $crawler = $client->request('GET', $this->getPath() . '/');
        $this->assertResponseOk();

        // Перейдём к добавлению  (форме)
        $link = $crawler->filter('a.course-new')->link();
        $client->click($link);
        $this->assertResponseOk();

        // Проверка передачи пустого значения в поле code
        // Заполнение полей формы
        $crawler = $client->submitForm('course-add', [
            'course[characterCode]' => '',
            'course[title]' => 'Новый курс',
            'course[description]' => 'Тестовый курс',
        ]);
        // Список ошибок
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Поле не может быть пустым', $error->text());

        // Проверка передачи значения более 255 символов в поле code
        // Заполнение полей формы
        $crawler = $client->submitForm('course-add', [
            'course[characterCode]' => 'sadjskadkasjdddddddasdkkkkkkkkk
            kkkkkkkasdkkkkkkkkkkkkkkkkkkasdllllllllllllllllllllllllll
            llllllllllllllllasdjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjj
            jjjjasdllllllllllllllllllllllllllllsadkasdkasdknqowhduiqbwd
            noskznmdoasmpodpasmdpamsdjashdfgugafduygfDISGFDUYFggxcgcxxx',
            'course[title]' => 'Новый курс',
            'course[description]' => 'Тестовый курс',
        ]);
        // Список ошибок
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Превышено максималльное значение символов', $error->text());


        // Проверка на уникальность поля code
        // Заполнение полей формы
        $crawler = $client->submitForm('course-add', [
            'course[characterCode]' => 'PPBI',
            'course[title]' => 'Новый курс',
            'course[description]' => 'Тестовый курс',
        ]);
        // Список ошибок
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Это код уже используется для другого курса.', $error->text());
    }

    // Тест страницы добавления курса с невалидным полем name
    public function testCourseNewAddNotValidName(): void
    {
        $client = self::getClient();
        $crawler = $client->request('GET', $this->getPath() . '/');
        $this->assertResponseOk();

        // Перейдём к добавлению  (форме)
        $link = $crawler->filter('a.course-new')->link();
        $client->click($link);
        $this->assertResponseOk();

        // Проверка передачи пустого значения в поле name
        // Заполнение полей формы
        $crawler = $client->submitForm('course-add', [
            'course[characterCode]' => 'CODE',
            'course[title]' => '',
            'course[description]' => 'Тестовый курс',
        ]);
        // Список ошибок
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Поле не может быть пустым', $error->text());

        // Проверка передачи значения более 255 символов в поле name
        // Заполнение полей формы
        $crawler = $client->submitForm('course-add', [
            'course[characterCode]' => 'CODE',
            'course[title]' => 'sadjskadkasjdddddddasdkkkkkkkkk
            kkkkkkkasdkkkkkkkkkkkkkkkkkkasdllllllllllllllllllllllllll
            llllllllllllllllasdjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjj
            jjjjasdllllllllllllllllllllllllllllsadkasdkasdknqowhduiqbwd
            noskznmdoasmpodpasmdpamsdkjhgjhfjhgfjhfhjgfhgfhgfjgfjhghgfhg',
            'course[description]' => 'Тестовый курс',
        ]);
        // Список ошибок
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Превышено максималльное значение символов', $error->text());
    }

    // Тест страницы добавления курса с невалидным полем description
    public function testCourseNewAddNotValidDescription(): void
    {
        $client = self::getClient();
        $crawler = $client->request('GET', $this->getPath() . '/');
        $this->assertResponseOk();

        // Перейдём к добавлению  (форме)
        $link = $crawler->filter('a.course-new')->link();
        $client->click($link);
        $this->assertResponseOk();

        // Проверка передачи значения более 1000 символоами в поле description
        // Заполнение полей формы
        $crawler = $client->submitForm('course-add', [
            'course[characterCode]' => 'NORMALCODE',
            'course[title]' => 'Новый курс',
            'course[description]' => 'sadjskadkasjdddddddasdkkkkkk
            kkkkkkkkkkasdkkkkkkkkkkkkkkkkkkasdllllllllllllllllllll
            llllllllllllllllllllllasdjjjjjjjjjjjjjjjjjjjjjjjjjjjjj
            jjjjjjjjjjjjjjjasdllllllllllllllllllllllllllllsadkasdk
            asdknqowhduiqbwdnoskznmdoasmpodpasmdpamsdsadddddddddda
            sssssssssssssssssssssssssssssssssssssssssssssssddddddd
            dddddddddddddddddddddddddddddddddddddddddddddddddddddd
            dddddddddddddddddddddddddddsssssssssssssssssssssssssss
            ssssssssssssssssssssssssssssssssssssssssssssssssssssss
            ssssssssssssssssssssssssssssssssssssssssssssssssssssss
            sssssadjskadkasjdddddddasdkkkkkkkkkkkkkkkkasdkkkkkkkkk
            kkkkkkkkkasdllllllllllllllllllllllllllllllllllllllllll
            asdjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjasdllll
            llllllllllllllllllllllllsadkasdkasdknqowhduiqbwdnoskzn
            mdoasmpodpasmdpamsdsaddddddddddasssssssssssssssssssssss
            ssssssssssssssssssssssssdddddddddddddddddddddddddddddd
            dddddddddddddddddddddddddddddddddddddddddddddddddddddd
            ddddssssssssssssssssssssssssssssssssssssssssssssssssss
            sssssssssssssssssssssssssssssssssssssssssssssssssssss',
        ]);
        // Список ошибок
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Превышено максималльное значение символов', $error->text());
    }

    // Тест страницы редактирование курса, а именно - изменение полей и редирект на испарвленный курс
    // Проверка валдиации формы мы проверили в тестах выше
    public function testCourseEditAndCheckFields(): void
    {
        $client = self::getClient();
        $crawler = $client->request('GET', $this->getPath() . '/');
        $this->assertResponseOk();

        // Перейдем к редактированию, допустим, первого курса на странице
        $link = $crawler->filter('a.card-link')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // Нажимаем кнопку редактирования
        $link = $crawler->filter('a.course-edit')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // Изменим значения полей формы
        $form = $crawler->selectButton('course-add')->form();
        // Получим id кода из формы
        $em = self::getEntityManager();
        $course = $em->getRepository(Course::class)->findOneBy(['characterCode' => $form['course[characterCode]']->getValue()]);
        // Изменяем поля в форме
        $form['course[characterCode]'] = 'NORMALCODE';
        $form['course[title]'] = 'NORMAL COURSE';
        $form['course[description]'] = 'TEST COURSE';
        // Отправляем форму
        $client->submit($form);

        // Проверяем редирект на изменённый курс
        self::assertTrue($client->getResponse()->isRedirect($this->getPath() . '/' . $course->getId()));
        // Переходим на страницу редиректа
        $crawler = $client->followRedirect();
        $this->assertResponseOk();
    }
}