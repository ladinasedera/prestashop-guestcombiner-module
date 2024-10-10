<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class GuestCombiner extends Module
{
    public function __construct()
    {
        $this->name = 'guestcombiner';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Ladina Sedera';
        $this->email = 'ladina.sedera@gmail.com';
        $this->website = 'https://ladinasedera.github.io';
        $this->need_instance = 0;

        parent::__construct();

        $this->displayName = $this->l('Guest to Customer Combiner');
        $this->description = $this->l('This module combines all guest accounts with normal customer accounts or creates new customer accounts if none exist.');
    }

    public function install()
    {
        return parent::install();
    }

    public function uninstall()
    {
        return parent::uninstall();
    }

    /**
     * Merge all guest accounts into normal customer accounts
     */
    public function mergeAllGuestAccounts()
    {
        // Step 1: Get all guest accounts from the database
        $guests = Db::getInstance()->executeS('SELECT * FROM '._DB_PREFIX_.'customer WHERE is_guest = 1');

        if (!$guests) {
            return false; // No guest accounts found
        }

        // Step 2: Loop through each guest account and process them
        foreach ($guests as $guest) {
            $guestEmail = $guest['email'];

            // Step 3: Check if a normal customer exists with the same email
            $customer = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'customer WHERE email = "'.pSQL($guestEmail).'" AND is_guest = 0');

            if ($customer) {
                // Step 4: Merge guest account into the existing customer account
                $customer_id = (int)$customer['id_customer'];
                $guest_id = (int)$guest['id_customer'];

                // Transfer guest's orders to the normal customer account
                Db::getInstance()->execute('UPDATE '._DB_PREFIX_.'orders SET id_customer = '.$customer_id.' WHERE id_customer = '.$guest_id);

                // Transfer guest's carts, addresses, etc.
                Db::getInstance()->execute('UPDATE '._DB_PREFIX_.'cart SET id_customer = '.$customer_id.' WHERE id_customer = '.$guest_id);
                Db::getInstance()->execute('UPDATE '._DB_PREFIX_.'address SET id_customer = '.$customer_id.' WHERE id_customer = '.$guest_id);

                // Optionally delete the guest account after merging
                Db::getInstance()->execute('DELETE FROM '._DB_PREFIX_.'customer WHERE id_customer = '.$guest_id);

            } else {
                // Step 5: No normal customer exists, so create a new customer account
                $newCustomer = new Customer();
                $newCustomer->firstname = $guest['firstname'];
                $newCustomer->lastname = $guest['lastname'];
                $newCustomer->email = $guest['email'];
                $newCustomer->passwd = Tools::encrypt(Tools::passwdGen(8)); // Generate a random password
                $newCustomer->id_gender = $guest['id_gender'];
                $newCustomer->birthday = $guest['birthday'];
                $newCustomer->newsletter = $guest['newsletter'];
                $newCustomer->optin = $guest['optin'];
                $newCustomer->add(); // Save new customer account

                $newCustomerId = $newCustomer->id;

                // Transfer guest's orders, carts, addresses to the new customer account
                Db::getInstance()->execute('UPDATE '._DB_PREFIX_.'orders SET id_customer = '.$newCustomerId.' WHERE id_customer = '.(int)$guest['id_customer']);
                Db::getInstance()->execute('UPDATE '._DB_PREFIX_.'cart SET id_customer = '.$newCustomerId.' WHERE id_customer = '.(int)$guest['id_customer']);
                Db::getInstance()->execute('UPDATE '._DB_PREFIX_.'address SET id_customer = '.$newCustomerId.' WHERE id_customer = '.(int)$guest['id_customer']);

                // Optionally delete the guest account after processing
                Db::getInstance()->execute('DELETE FROM '._DB_PREFIX_.'customer WHERE id_customer = '.(int)$guest['id_customer']);
            }
        }

        return true; // All guest accounts processed
    }

    /**
     * Display module configuration page with a button to trigger the merging process.
     */
    public function getContent()
    {
        $output = '';
        if (Tools::isSubmit('submitMergeAllGuests')) {
            if ($this->mergeAllGuestAccounts()) {
                $output .= $this->displayConfirmation($this->l('All guest accounts have been successfully merged or converted to customer accounts.'));
            } else {
                $output .= $this->displayError($this->l('No guest accounts found.'));
            }
        }

        return $output.$this->renderMergeAllForm();
    }

    /**
     * Renders the form with the button to merge all guest accounts
     */
    public function renderMergeAllForm()
    {
        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Merge All Guest Accounts'),
                ],
                'submit' => [
                    'title' => $this->l('Merge All Guests'),
                    'class' => 'btn btn-default pull-right',
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->submit_action = 'submitMergeAllGuests';

        return $helper->generateForm([$fields_form]);
    }
}
