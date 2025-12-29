-- Add Stripe customer reference columns for users, companies and payment methods
-- Safe to run multiple times with IF NOT EXISTS checks

-- Users (hus) table lives in workz_data database
ALTER TABLE `hus`
    ADD COLUMN IF NOT EXISTS `stripe_customer_id` VARCHAR(255) NULL AFTER `ml`;

-- Companies (companies) table in workz_companies database (adjust schema if different)
ALTER TABLE `companies`
    ADD COLUMN IF NOT EXISTS `stripe_customer_id` VARCHAR(255) NULL AFTER `ml`;

-- Billing payment methods (workz_data.billing_payment_methods)
ALTER TABLE `billing_payment_methods`
    ADD COLUMN IF NOT EXISTS `stripe_customer_id` VARCHAR(255) NULL AFTER `token_ref`;
