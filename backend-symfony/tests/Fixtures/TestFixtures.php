<?php

namespace App\Tests\Fixtures;

use App\Entity\User;
use App\Entity\Vehicle;
use App\Entity\VehicleModel;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class TestFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        // Create test user
        $user = new User();
        $user->setEmail('test@checkengine.local');
        $user->setPassword($this->passwordHasher->hashPassword($user, 'test123'));
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setRoles(['ROLE_USER']);
        $user->setCreatedAt(new \DateTimeImmutable());
        $manager->persist($user);

        // Create vehicle model
        $model = new VehicleModel();
        $model->setManufacturer('Toyota');
        $model->setModel('Corolla');
        $model->setYearStart(2020);
        $model->setYearEnd(2024);
        $model->setGeneration('E210');
        $model->setEngineCode('2ZR-FE');
        $model->setDisplacement('1.8');
        $model->setFuelType('Gasoline');
        $model->setHorsePower(139);
        $model->setHybrid(false);
        $manager->persist($model);

        // Create test vehicle
        $vehicle = new Vehicle();
        $vehicle->setOwner($user);
        $vehicle->setModel($model);
        $vehicle->setNickname('Test Car');
        $vehicle->setYear(2020);
        $vehicle->setVin('1HGBH41JXMN109186');
        $vehicle->setLicensePlate('TEST123');
        $vehicle->setMileage(50000);
        $vehicle->setCreatedAt(new \DateTimeImmutable());
        $manager->persist($vehicle);

        $manager->flush();

        // Store references for tests
        $this->addReference('test-user', $user);
        $this->addReference('test-vehicle', $vehicle);
    }
}
