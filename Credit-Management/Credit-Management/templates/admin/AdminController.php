<?php
class AdminController {
    public function view_vendor_credits() {
        $vendor = $this->input->get('vendor');
        $service = $this->input->get('service');

        $this->load->model('CreditModel');
        $data['credits'] = $this->CreditModel->get_vendor_credits($vendor, $service);

        $this->load->view('admin/vendor_credits', $data);
    }
}
?>