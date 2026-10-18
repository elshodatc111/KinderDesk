<?php

return [
    'required' => 'Поле «:attribute» обязательно для заполнения.',
    'string' => 'Поле «:attribute» должно быть строкой.',
    'numeric' => 'Поле «:attribute» должно быть числом.',
    'integer' => 'Поле «:attribute» должно быть целым числом.',
    'email' => 'Поле «:attribute» должно быть корректным email.',
    'date' => 'Поле «:attribute» должно быть датой.',
    'in' => 'Выбрано недопустимое значение поля «:attribute».',
    'exists' => 'Выбрано недопустимое значение поля «:attribute».',
    'unique' => 'Такое значение поля «:attribute» уже существует.',
    'confirmed' => 'Подтверждение поля «:attribute» не совпадает.',
    'image' => 'Поле «:attribute» должно быть изображением.',
    'mimes' => 'Поле «:attribute» должно быть файлом типа: :values.',
    'before_or_equal' => 'Поле «:attribute» не может быть позже :date.',
    'min' => ['string' => 'Поле «:attribute» должно содержать не менее :min символов.', 'numeric' => 'Значение «:attribute» должно быть не меньше :min.', 'file' => 'Файл «:attribute» должен быть не меньше :min КБ.', 'array' => 'Выберите не менее :min элементов в «:attribute».'],
    'max' => ['string' => 'Поле «:attribute» не должно превышать :max символов.', 'numeric' => 'Значение «:attribute» не должно быть больше :max.', 'file' => 'Файл «:attribute» не должен превышать :max КБ.', 'array' => 'В «:attribute» не более :max элементов.'],
    'between' => ['numeric' => 'Значение «:attribute» должно быть от :min до :max.'],
    'attributes' => ['name' => 'имя', 'phone' => 'телефон', 'password' => 'пароль', 'email' => 'email', 'amount' => 'сумма', 'date' => 'дата', 'reason' => 'причина', 'role_id' => 'должность', 'group_id' => 'группа', 'logo' => 'логотип', 'description' => 'описание', 'category' => 'категория'],
];
