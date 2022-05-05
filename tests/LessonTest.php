<?php

namespace App\Tests;

use App\DataFixtures\AppFixtures;
use App\Entity\Course;
use App\Entity\Lesson;

class LessonTest extends AbstractTest
{
    // Стартовая страница курсов
    private $startingPathCourse = '/courses';
    // Стартовая страница уроков
    private $startingPathLesson = '/lessons';

    // Метод вызова старовой страницы курсов
    public function getPathCourse(): string
    {
        return $this->startingPathCourse;
    }

    // Метод вызова старовой страницы уроков
    public function getPathLesson(): string
    {
        return $this->startingPathLesson;
    }

    // Переопределение метода для фикстур
    protected function getFixtures(): array
    {
        return [AppFixtures::class];
    }

    // Проверка на корректный http-статус для всех уроков по всем курсам
    public function testPageIsSuccessful(): void
    {
        // Перейдём на главную с курсами
        $client = self::getClient();
        $crawler = $client->request('GET', $this->getPathCourse() . '/');
        $this->assertResponseOk();

        // Переходим по курсам к их урокам
        $courseLinks = $crawler->filter('a.card-link')->links();
        foreach ($courseLinks as $courseLink) {
            $crawler = $client->click($courseLink);
            $this->assertResponseOk();

            // Переходим по всем урокам данного курса
            $lessonLinks = $crawler->filter('a.lesson-link')->links();
            foreach ($lessonLinks as $lessonLink) {
                $client->click($lessonLink);
                self::assertResponseIsSuccessful();
            }
        }
    }

    // Провекра перехода на несуществующий урок
    public function testPageIsNotFound(): void
    {
        $client = self::getClient();
        $client->request('GET', $this->getPathLesson() . '/-1');
        $this->assertResponseNotFound();
    }

    // Тест страницы добавления урока с валидными значениями,
    // А также проверить удаление урока
    // А также редирект на страницу курса после добалвения и удаления урока
    public function testLessonNewAddValidFieldsAndDeleteCourse(): void
    {
        $client = self::getClient();
        $crawler = $client->request('GET', $this->getPathCourse() . '/');
        $this->assertResponseOk();

        // Перейдём к первому курсу по ссылке
        $link = $crawler->filter('a.card-link')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // Перейдём к добавлению  (форме)
        $link = $crawler->filter('a.lesson-new')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // Заполнение полей формы
        $form = $crawler->selectButton('lesson-add')->form();
        // Изменяем поля в форме
        $form['lesson[title]'] = 'Новый урок';
        $form['lesson[content]'] = 'Тестовый материал';
        $form['lesson[number]'] = '1';
        // Получим id созданного курса
        $em = static::getEntityManager();
        $course = $em->getRepository(Course::class)->findOneBy(['id' => $form['lesson[course]']->getValue()]);
        self::assertNotEmpty($course);
        // Отправляем форму
        $client->submit($form);
        // Проверка редиректа на страницу курса
        self::assertTrue($client->getResponse()->isRedirect($this->getPathCourse() . '/' . $course->getId()));
        // Переходим на страницу добавленного урока
        $crawler = $client->followRedirect();
        $this->assertResponseOk();

        // Перейдём на страницу добавленного урока
        $link = $crawler->filter('ol > li > a')->first()->link();
        $client->click($link);
        $this->assertResponseOk();

        // Нажимаме кнопку удалить
        $client->submitForm('lesson-delete');
        // Проверка редиректа на страницу курса
        self::assertTrue($client->getResponse()->isRedirect($this->getPathCourse() . '/' . $course->getId()));
        // Переходим на страницу редиректа
        $client->followRedirect();
        $this->assertResponseOk();
    }

    // Тест страницы добавления курса с невалидным полем name
    public function testLessonNewAddNotValidName(): void
    {
        $client = self::getClient();
        $crawler = $client->request('GET', $this->getPathCourse() . '/');
        $this->assertResponseOk();

        $link = $crawler->filter('a.card-link')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // Перейдём к добавлению  (форме)
        $link = $crawler->filter('a.lesson-new')->link();
        $client->click($link);
        $this->assertResponseOk();

        // Проверка передачи пустого значения в поле title
        // Заполнение полей формы
        $crawler = $client->submitForm('lesson-add', [
            'lesson[title]' => '',
            'lesson[content]' => 'Новый урок',
            'lesson[number]' => '3',
        ]);
        // Список ошибок
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Поле не может быть пустым', $error->text());

        // Проверка передачи значения более 255 символов в поле code
        // Заполнение полей формы
        $crawler = $client->submitForm('lesson-add', [
            'lesson[title]' => 'sadjskadkasjdddddddasdkkkkkkkkk
            kkkkkkkasdkkkkkkkkkkkkkkkkkkasdllllllllllllllllllllllllll
            llllllllllllllllasdjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjj
            jjjjasdllllllllllllllllllllllllllllsadkasdkasdknqowhduiqbwd
            noskznmdoasmpodpasmdpamsddddddddddddddddddddddddddddddddddd',
            'lesson[content]' => 'Новый урок',
            'lesson[number]' => '13',
        ]);
        // Список ошибок
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Превышено максималльное значение символов', $error->text());
    }

    // Тест страницы добавления урока с невалидным полем content
    public function testLessonNewAddNotValidMaterial(): void
    {
        $client = self::getClient();
        $crawler = $client->request('GET', $this->getPathCourse() . '/');
        $this->assertResponseOk();

        $link = $crawler->filter('a.card-link')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // Перейдём к добавлению  (форме)
        $link = $crawler->filter('a.lesson-new')->link();
        $client->click($link);
        $this->assertResponseOk();

        // Проверка передачи пустого значения в поле content
        // Заполнение полей формы
        $crawler = $client->submitForm('lesson-add', [
            'lesson[title]' => 'Новый урок',
            'lesson[content]' => '',
            'lesson[number]' => '13',
        ]);
        // Список ошибок
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Поле не может быть пустым', $error->text());
    }

    // Тест страницы добавления урока с невалидным полем number
    public function testLessonNewAddNotValidNumber(): void
    {
        $client = self::getClient();
        $crawler = $client->request('GET', $this->getPathCourse() . '/');
        $this->assertResponseOk();

        $link = $crawler->filter('a.card-link')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // Перейдём к добавлению  (форме)
        $link = $crawler->filter('a.lesson-new')->link();
        $client->click($link);
        $this->assertResponseOk();

        // Проверка передачи пустого значения в поле number
        // Заполнение полей формы
        $crawler = $client->submitForm('lesson-add', [
            'lesson[title]' => 'Новый урок',
            'lesson[content]' => 'Новый материал',
            'lesson[number]' => '',
        ]);
        // Список ошибок
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Поле не может быть пустым', $error->text());

        // Проверка передачи значения неверной валидации номера
        // Заполнение полей формы
        $crawler = $client->submitForm('lesson-add', [
            'lesson[title]' => 'Новый урок',
            'lesson[content]' => 'Новый материал',
            'lesson[number]' => 'sdk1',
        ]);
        // Список ошибок
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('This value is not valid.', $error->text());
    }

    // Тест страницы редактирование урока, а именно - изменение полей и редирект на испарвленный урок
    public function testLessonEditAndCheckFields(): void
    {
        $client = self::getClient();
        $crawler = $client->request('GET', $this->getPathCourse() . '/');
        $this->assertResponseOk();

        $link = $crawler->filter('a.card-link')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // Перейдём к первому уроку
        $link = $crawler->filter('ol > li > a')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // Нажмём на ссылку редактирования урока
        $link = $crawler->filter('a.lesson-edit')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // Заполнение полей формы
        $form = $crawler->selectButton('lesson-add')->form();
        // Получаем урок по номеру
        $em = self::getEntityManager();
        $lesson = $em->getRepository(Lesson::class)->findOneBy([
            'number' => $form['lesson[number]']->getValue(),
            'course' => $form['lesson[course]']->getValue(),
        ]);
        // Изменяем поля в форме
        $form['lesson[title]'] = 'New lesson';
        $form['lesson[content]'] = 'Test';
        // Отправляем форму
        $client->submit($form);
        // Проверка редиректа на страницу урока
        self::assertTrue($client->getResponse()->isRedirect($this->getPathLesson() . '/' . $lesson->getId()));
        // Переходим на страницу редиректа
        $crawler = $client->followRedirect();
        $this->assertResponseOk();
    }
}
