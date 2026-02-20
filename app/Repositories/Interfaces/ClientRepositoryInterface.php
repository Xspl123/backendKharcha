<?php
    namespace App\Repositories\Interfaces;

    interface ClientRepositoryInterface
    {
        public function getAll();
        public function store(array $data);
        public function show($id);
        public function update($id, array $data);
        public function delete($id);
    }



?>