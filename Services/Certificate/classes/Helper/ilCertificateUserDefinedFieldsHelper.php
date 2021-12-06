<?php
/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * @author  Niels Theen <ntheen@databay.de>
 */
class ilCertificateUserDefinedFieldsHelper
{
    /**
     * @return ilUserDefinedFields
     */
    public function createInstance()
    {
        return ilUserDefinedFields::_getInstance();
    }
}
